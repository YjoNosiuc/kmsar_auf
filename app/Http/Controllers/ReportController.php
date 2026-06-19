<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Research;
use App\Models\User;
use App\Services\ReportGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private const STATUS_VALUES = [
        'published_scopus',
        'published_non_indexed',
        'presented_external',
        'presented_internal',
        'patent_granted',
        'patent_submitted',
        'completed_unpublished',
        'ongoing',
        'proposal',
    ];

    private const CLASSIFICATION_VALUES = [
        'self_funded',
        'internally_funded',
        'externally_funded',
        'thesis',
        'thesis_dissertation',
        'collaboration',
        'other',
    ];

    private const APPROVAL_STAGE_VALUES = [
        'draft',
        'dean_review',
        'ovpri_review',
        'approved',
        'rejected',
    ];

    public function __construct(
        private ReportGeneratorService $reportGenerator
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $perPage = min(50, max(10, $request->integer('per_page', 10)));
        $page = max(1, $request->integer('page', 1));

        if ($user->hasRole(['ovpri_admin', 'cdaic_admin', 'super_admin'])) {
            $colleges = College::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            $faculties = User::query()
                ->role('faculty')
                ->orderBy('name')
                ->get();

            $filters = $this->extractFiltersFromRequest($request, false);
            $query = $this->reportQuery($filters, false, null);
            $totalCount = (clone $query)->count();
            $preview = (clone $query)
                ->orderByDesc('created_at')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return view('reports.index', [
                'reportScope' => 'ovpri',
                'pageSubtitle' => __('Generate and export filtered institutional research reports'),
                'colleges' => $colleges,
                'faculties' => $faculties,
                'preview' => $preview,
                'totalCount' => $totalCount,
                'filters' => $filters,
                'page' => $page,
                'perPage' => $perPage,
                'reportGenerator' => $this->reportGenerator,
                'collegeName' => null,
                'reportStats' => [
                    'matching' => $totalCount,
                    'scopus' => $this->countWithExtraWhere($filters, false, null, fn (Builder $q) => $q->where('status', 'published_scopus')),
                    'colleges_or_faculty' => $this->distinctCollegeCount($filters),
                ],
            ]);
        }

        if ($user->hasRole(['college_dean', 'unit_head'])) {
            $collegeId = (int) $user->college_id;

            abort_if($collegeId === 0, 403);

            $college = College::query()->find($collegeId);
            $collegeName = $college?->name ?? '';

            $faculties = User::query()
                ->role('faculty')
                ->where('college_id', $collegeId)
                ->orderBy('name')
                ->get();

            $filters = $this->extractFiltersFromRequest($request, true);
            $query = $this->reportQuery($filters, true, $collegeId);
            $totalCount = (clone $query)->count();
            $preview = (clone $query)
                ->orderByDesc('created_at')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get();

            return view('reports.index', [
                'reportScope' => 'college',
                'pageSubtitle' => __(':college — Research Reports', ['college' => $collegeName]),
                'colleges' => collect(),
                'faculties' => $faculties,
                'preview' => $preview,
                'totalCount' => $totalCount,
                'filters' => $filters,
                'page' => $page,
                'perPage' => $perPage,
                'reportGenerator' => $this->reportGenerator,
                'collegeName' => $collegeName,
                'collegeId' => $collegeId,
                'reportStats' => [
                    'matching' => $totalCount,
                    'published' => $this->countWithExtraWhere($filters, true, $collegeId, fn (Builder $q) => $q->whereIn('status', ['published_scopus', 'published_non_indexed'])),
                    'presented' => $this->countWithExtraWhere($filters, true, $collegeId, fn (Builder $q) => $q->whereIn('status', ['presented_internal', 'presented_external'])),
                ],
            ]);
        }

        abort(403);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();

        $validated = $request->validate([
            'report_type' => ['required', Rule::in(['ovpri', 'college'])],
            'format' => ['required', Rule::in(['pdf', 'excel'])],
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'registration_type' => ['nullable', Rule::in(['new', 'update'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'research_classification' => ['nullable', Rule::in(self::CLASSIFICATION_VALUES)],
            'status' => ['nullable', Rule::in(self::STATUS_VALUES)],
            'approval_stage' => ['nullable', Rule::in(self::APPROVAL_STAGE_VALUES)],
            'faculty' => ['nullable', 'integer', 'exists:users,id'],
            'sdg' => ['nullable', 'integer', 'between:1,17'],
            'funding_agency' => ['nullable', 'string', 'max:100'],
            'academic_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'include_rejected' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $filters = $this->normalizeFilters($validated);

        $filtersForPdfSummary = $filters;

        if ($validated['report_type'] === 'ovpri') {
            if (! $user->hasAnyRole(['super_admin', 'ovpri_admin', 'cdaic_admin'])) {
                abort(403);
            }

            $researches = $this->reportQuery($filters, false, null)
                ->orderByDesc('created_at')
                ->get();
            $collegeReport = false;
            $title = __('University research report (OVPRI)');
            $collegeId = null;
        } else {
            if (! $user->hasAnyRole(['college_dean', 'unit_head'])) {
                abort(403);
            }

            $collegeId = (int) $user->college_id;
            abort_if($collegeId === 0, 403);

            if (! empty($filters['college_id']) && (int) $filters['college_id'] !== $collegeId) {
                abort(403);
            }

            if (! empty($filters['faculty'])) {
                $facultyUser = User::query()->find((int) $filters['faculty']);
                if (! $facultyUser || (int) $facultyUser->college_id !== $collegeId) {
                    abort(403);
                }
            }

            unset($filters['college_id']);

            $researches = $this->reportQuery($filters, true, $collegeId)
                ->orderByDesc('created_at')
                ->get();
            $collegeReport = true;
            $title = __('College research report');
        }

        $appliedFilters = $this->buildPdfAppliedFilterLines(
            $filtersForPdfSummary,
            $collegeReport,
            $collegeReport ? $collegeId : null
        );

        if ($validated['format'] === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ResearchReportExport($researches, $this->reportGenerator, $collegeReport),
                'KMSAR-Report-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.pdf', [
            'records' => $researches,
            'reportTitle' => $title,
            'filters' => $appliedFilters,
            'recordCount' => $researches->count(),
            'generatedAt' => now()->format('F d, Y h:i A'),
            'role' => auth()->user()->getRoleNames()->first(),
            'report_type' => $validated['report_type'],
        ])
            ->setOption('enable_php', true)
            ->setPaper('a4', 'landscape');

        return $pdf->stream('KMSAR-Report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Human-readable filter lines for PDF export (names and labels, not raw IDs).
     *
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    protected function buildPdfAppliedFilterLines(array $filters, bool $collegeReport, ?int $collegeId): array
    {
        $lines = [];
        $service = $this->reportGenerator;

        if ($collegeReport && $collegeId !== null) {
            $college = College::query()->find($collegeId);
            $lines[] = __('College: :name', ['name' => $college?->name ?? (string) $collegeId]);
        } elseif (! empty($filters['college_id'])) {
            $college = College::query()->find((int) $filters['college_id']);
            $lines[] = __('College: :name', ['name' => $college ? $college->name : $filters['college_id']]);
        }

        if (! empty($filters['registration_type'])) {
            $label = match ((string) $filters['registration_type']) {
                'new' => __('New Registration'),
                'update' => __('Update Existing Record'),
                default => (string) $filters['registration_type'],
            };
            $lines[] = __('Registration type: :t', ['t' => $label]);
        }

        if (! empty($filters['date_from'])) {
            $lines[] = __('Date From: :d', [
                'd' => Carbon::parse($filters['date_from'])->format('F d, Y'),
            ]);
        }

        if (! empty($filters['date_to'])) {
            $lines[] = __('Date To: :d', [
                'd' => Carbon::parse($filters['date_to'])->format('F d, Y'),
            ]);
        }

        if (! empty($filters['research_classification'])) {
            $lines[] = __('Classification: :c', [
                'c' => $service->classificationLabel((string) $filters['research_classification']),
            ]);
        }

        if (! empty($filters['status'])) {
            $lines[] = __('Research progress: :s', [
                's' => $service->statusLabel((string) $filters['status']),
            ]);
        }

        if (! empty($filters['approval_stage'])) {
            $lines[] = __('Approval status: :s', [
                's' => $this->approvalStageLabel((string) $filters['approval_stage']),
            ]);
        }

        if (! empty($filters['sdg'])) {
            $lines[] = __('SDG :n', ['n' => (int) $filters['sdg']]);
        }

        if (! empty($filters['funding_agency'])) {
            $lines[] = __('Funding agency: :a', ['a' => $filters['funding_agency']]);
        }

        if (! empty($filters['academic_year'])) {
            $lines[] = __('Academic year: :y', ['y' => (int) $filters['academic_year']]);
        }

        if (! empty($filters['faculty'])) {
            $facultyUser = User::query()->find((int) $filters['faculty']);
            $lines[] = __('Primary author: :name', [
                'name' => $facultyUser?->name ?? (string) $filters['faculty'],
            ]);
        }

        if (($filters['include_rejected'] ?? '0') !== '1') {
            $lines[] = __('Rejected records: excluded');
        } else {
            $lines[] = __('Rejected records: included');
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractFiltersFromRequest(Request $request, bool $collegeContext): array
    {
        $keys = [
            'date_from',
            'date_to',
            'research_classification',
            'status',
            'approval_stage',
            'sdg',
            'funding_agency',
            'academic_year',
            'include_rejected',
            'registration_type',
        ];

        if (! $collegeContext) {
            $keys[] = 'college_id';
        } else {
            $keys[] = 'faculty';
        }

        return $this->normalizeFilters($request->only($keys));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function normalizeFilters(array $input): array
    {
        $filters = array_filter(
            $input,
            static fn ($v) => $v !== null && $v !== ''
        );

        if (! isset($filters['include_rejected'])) {
            $filters['include_rejected'] = '0';
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function reportQuery(array $filters, bool $collegeScoped, ?int $scopedCollegeId): Builder
    {
        $query = Research::query()->with([
            'primaryAuthor',
            'motherCollege',
            'researchAuthors',
        ]);

        if ($collegeScoped && $scopedCollegeId !== null) {
            $query->where('mother_college_id', $scopedCollegeId);
        } elseif (! empty($filters['college_id'])) {
            $query->where('mother_college_id', (int) $filters['college_id']);
        }

        if (! empty($filters['faculty'])) {
            $query->where('primary_author_id', (int) $filters['faculty']);
        }

        if (! empty($filters['registration_type'])) {
            $query->where('registration_type', $filters['registration_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['research_classification'])) {
            $query->where('research_classification', $filters['research_classification']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['approval_stage'])) {
            $query->where('approval_stage', $filters['approval_stage']);
        }

        if (! empty($filters['sdg'])) {
            $query->whereJsonContains('sdg_tags', (int) $filters['sdg']);
        }

        if (! empty($filters['funding_agency'])) {
            $query->where('funding_agency', 'like', '%'.$filters['funding_agency'].'%');
        }

        if (! empty($filters['academic_year'])) {
            $query->whereYear('start_date', (int) $filters['academic_year']);
        }

        if (($filters['include_rejected'] ?? '0') !== '1') {
            $query->where('approval_stage', '!=', 'rejected');
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function countWithExtraWhere(array $filters, bool $collegeScoped, ?int $scopedCollegeId, callable $callback): int
    {
        $query = $this->reportQuery($filters, $collegeScoped, $scopedCollegeId);
        $callback($query);

        return $query->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function distinctCollegeCount(array $filters): int
    {
        return (int) $this->reportQuery($filters, false, null)
            ->whereNotNull('mother_college_id')
            ->distinct('mother_college_id')
            ->count('mother_college_id');
    }

    protected function approvalStageLabel(string $stage): string
    {
        return match ($stage) {
            'draft' => __('Draft'),
            'dean_review' => __('Dean review'),
            'ovpri_review' => __('OVPRI review'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            default => ucwords(str_replace('_', ' ', $stage)),
        };
    }

    public function download(Request $request, string $token): BinaryFileResponse
    {
        $payload = Cache::get('report_download:'.$token);

        if (! $payload || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()->id) {
            abort(403);
        }

        $path = $payload['path'] ?? null;
        $downloadName = $payload['download_name'] ?? 'report';

        if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->download($path, $downloadName);
    }
}
