<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\User;
use App\Services\ReportGeneratorService;
use Carbon\Carbon;
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
        'collaboration',
        'other',
    ];

    public function __construct(
        private ReportGeneratorService $reportGenerator
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

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

            $preview = $this->reportGenerator->ovpriReport($filters, 10);
            $totalCount = $this->reportGenerator->ovpriReportCount($filters);

            return view('reports.index', [
                'reportScope' => 'ovpri',
                'pageSubtitle' => __('Generate and export filtered institutional research reports'),
                'colleges' => $colleges,
                'faculties' => $faculties,
                'preview' => $preview,
                'totalCount' => $totalCount,
                'filters' => $filters,
                'reportGenerator' => $this->reportGenerator,
                'collegeName' => null,
                'reportStats' => [
                    'matching' => $totalCount,
                    'scopus' => $this->reportGenerator->ovpriScopusCount($filters),
                    'colleges_or_faculty' => $this->reportGenerator->ovpriDistinctCollegeCount($filters),
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

            $preview = $this->reportGenerator->collegeReport($collegeId, $filters, 10);
            $totalCount = $this->reportGenerator->collegeReportCount($collegeId, $filters);

            return view('reports.index', [
                'reportScope' => 'college',
                'pageSubtitle' => __(':college — Research Reports', ['college' => $collegeName]),
                'colleges' => collect(),
                'faculties' => $faculties,
                'preview' => $preview,
                'totalCount' => $totalCount,
                'filters' => $filters,
                'reportGenerator' => $this->reportGenerator,
                'collegeName' => $collegeName,
                'collegeId' => $collegeId,
                'reportStats' => [
                    'matching' => $totalCount,
                    'published' => $this->reportGenerator->collegePublishedCount($collegeId, $filters),
                    'presented' => $this->reportGenerator->collegePresentedCount($collegeId, $filters),
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
            'faculty' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $filters = [
            'college_id' => $validated['college_id'] ?? null,
            'registration_type' => $validated['registration_type'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'research_classification' => $validated['research_classification'] ?? null,
            'status' => $validated['status'] ?? null,
            'faculty' => $validated['faculty'] ?? null,
        ];

        if (($validated['report_type'] ?? '') === 'college') {
            unset($filters['registration_type']);
        }

        $filters = array_filter(
            $filters,
            static fn ($v) => $v !== null && $v !== ''
        );

        $filtersForPdfSummary = $filters;

        if ($validated['report_type'] === 'ovpri') {
            if (! $user->hasAnyRole(['super_admin', 'ovpri_admin', 'cdaic_admin'])) {
                abort(403);
            }

            $researches = $this->reportGenerator->ovpriReport($filters);
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

            $researches = $this->reportGenerator->collegeReport($collegeId, $filters);
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
            $raw = (string) $filters['research_classification'];
            $label = ucwords(str_replace('_', ' ', $raw));
            $lines[] = __('Classification: :c', ['c' => $label]);
        }

        if (! empty($filters['status'])) {
            $lines[] = __('Progress status: :s', [
                's' => $service->statusLabel((string) $filters['status']),
            ]);
        }

        if (! empty($filters['faculty'])) {
            $facultyUser = User::query()->find((int) $filters['faculty']);
            $lines[] = __('Primary author: :name', [
                'name' => $facultyUser?->name ?? (string) $filters['faculty'],
            ]);
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
        ];

        if (! $collegeContext) {
            $keys[] = 'college_id';
        } else {
            $keys[] = 'faculty';
        }

        return $request->only($keys);
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
