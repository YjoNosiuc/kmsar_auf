<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Program;
use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OvpriController extends Controller
{
    use AuthorizesRequests;

    private const COMPLETED_STATUSES = [
        'completed_unpublished',
        'presented_internal',
        'presented_external',
        'published_non_indexed',
        'published_scopus',
        'patent_granted',
    ];

    private const IN_PROGRESS_STATUSES = ['proposal', 'ongoing'];

    private const PRESENTED_STATUSES = ['presented_internal', 'presented_external'];

    private const CLASSIFICATION_LABELS = [
        'internally_funded' => 'Internally funded',
        'self_funded' => 'Self funded',
        'externally_funded' => 'Externally funded',
        'thesis' => 'Thesis',
        'other' => 'Other',
    ];

    public function dashboard(Request $request): View
    {
        $dateFrom = $request->filled('date_from') ? $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') ? $request->input('date_to') : null;
        $collegeTerm = trim((string) $request->input('college', ''));
        $selectedCollege = $this->resolveSelectedCollege($collegeTerm);
        $cacheSuffix = ($dateFrom ?? 'all').'_'.($dateTo ?? 'all');

        $stats = Cache::remember(
            'ovpri_dash_v5_'.$cacheSuffix.'_'.now()->format('Y-m-d-H'),
            3600,
            fn () => $this->buildDashboardStats($dateFrom, $dateTo)
        );

        $sdgNames = [
            1 => 'No Poverty', 2 => 'Zero Hunger', 3 => 'Good Health',
            4 => 'Quality Education', 5 => 'Gender Equality', 6 => 'Clean Water',
            7 => 'Clean Energy', 8 => 'Decent Work', 9 => 'Innovation',
            10 => 'Reduced Inequalities', 11 => 'Sustainable Cities', 12 => 'Responsible Consumption',
            13 => 'Climate Action', 14 => 'Life Below Water', 15 => 'Life on Land',
            16 => 'Peace & Justice', 17 => 'Partnerships',
        ];

        $sdgDistribution = Cache::remember(
            'sdg_counts_v2_'.$cacheSuffix,
            3600,
            fn () => $this->buildSdgDistribution($dateFrom, $dateTo, $sdgNames)
        );

        return view('ovpri.dashboard', [
            'totalResearch' => $stats['totalResearch'],
            'researchInProgress' => $stats['researchInProgress'],
            'pendingApprovals' => $stats['pendingApprovals'],
            'scopusCount' => $stats['scopusCount'],
            'researchByCollege' => $stats['researchByCollege'],
            'collegeBreakdown' => $stats['collegeBreakdown'],
            'scopusByCollege' => $stats['scopusByCollege'],
            'presentedByCollege' => $stats['presentedByCollege'],
            'classificationBreakdown' => $stats['classificationBreakdown'],
            'workflowStatus' => $stats['workflowStatus'],
            'sdgDistribution' => $sdgDistribution,
            'monthlyTrend' => $stats['monthlyTrend'],
            'submissionTrend' => $stats['submissionTrend'],
            'engagedTotal' => $stats['engagedTotal'],
            'engagedByCollege' => $stats['engagedByCollege'],
            'selectedCollege' => $selectedCollege,
            'programBreakdown' => $this->buildProgramBreakdown($selectedCollege, $dateFrom, $dateTo),
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'collegeTerm' => $collegeTerm,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardStats(?string $dateFrom, ?string $dateTo): array
    {
        $base = $this->baseResearchQuery($dateFrom, $dateTo);
        $completed = $this->reportEligibleResearchQuery($dateFrom, $dateTo);

        $totalResearch = (clone $completed)->count();
        $researchInProgress = (clone $base)->whereIn('status', self::IN_PROGRESS_STATUSES)->count();

        $pendingApprovals = (clone $base)
            ->where('approval_stage', 'ovpri_review')
            ->whereNotNull('submitted_at')
            ->count();

        $scopusCount = (clone $base)
            ->where(function ($q) {
                $q->where('is_scopus_indexed', true)
                    ->orWhere('status', 'published_scopus');
            })
            ->count();

        $researchCountsByCollege = (clone $completed)
            ->selectRaw('mother_college_id, count(*) as total')
            ->groupBy('mother_college_id')
            ->pluck('total', 'mother_college_id');

        $scopusCountsByCollege = (clone $base)
            ->where('is_scopus_indexed', true)
            ->selectRaw('mother_college_id, count(*) as total')
            ->groupBy('mother_college_id')
            ->pluck('total', 'mother_college_id');

        $presentedCountsByCollege = (clone $base)
            ->whereIn('status', self::PRESENTED_STATUSES)
            ->selectRaw('mother_college_id, count(*) as total')
            ->groupBy('mother_college_id')
            ->pluck('total', 'mother_college_id');

        $colleges = College::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $researchByCollege = $colleges->map(fn (College $c) => [
            'label' => $c->code,
            'code' => $c->code,
            'name' => $c->name,
            'count' => (int) ($researchCountsByCollege[$c->id] ?? 0),
        ]);

        $totalForChart = (int) $researchByCollege->sum('count');
        $collegeBreakdown = $researchByCollege->map(function (array $item) use ($totalForChart) {
            $item['percentage'] = $totalForChart > 0
                ? round($item['count'] / $totalForChart * 100, 1)
                : 0.0;

            return $item;
        });
        $researchByCollege = $collegeBreakdown;

        $scopusByCollege = $colleges->map(fn (College $c) => [
            'label' => $c->code,
            'name' => $c->name,
            'count' => (int) ($scopusCountsByCollege[$c->id] ?? 0),
        ]);

        $presentedByCollege = $colleges->map(fn (College $c) => [
            'label' => $c->code,
            'name' => $c->name,
            'count' => (int) ($presentedCountsByCollege[$c->id] ?? 0),
        ]);

        $classificationBreakdown = $this->buildClassificationBreakdown($completed);

        $rejectedQuery = Research::query()->where('approval_stage', 'rejected');
        if ($dateFrom) {
            $rejectedQuery->whereDate('start_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $rejectedQuery->whereDate('start_date', '<=', $dateTo);
        }

        $workflowStatus = collect([
            ['key' => 'ovpri_review', 'label' => __('OVPRI Review'), 'count' => (clone $base)->where('approval_stage', 'ovpri_review')->count()],
            ['key' => 'approved', 'label' => __('Approved'), 'count' => (clone $base)->where('approval_stage', 'approved')->count()],
            ['key' => 'rejected', 'label' => __('Rejected'), 'count' => $rejectedQuery->count()],
        ]);

        $trendYear = $dateFrom
            ? (int) date('Y', strtotime((string) $dateFrom))
            : (int) now()->year;
        $isSqlite = Research::query()->getConnection()->getDriverName() === 'sqlite';
        $monthlyQuery = clone $completed;

        if ($dateFrom || $dateTo) {
            $monthlyTotals = $isSqlite
                ? $monthlyQuery
                    ->selectRaw('CAST(strftime(\'%m\', start_date) AS INTEGER) as month, count(*) as total')
                    ->groupByRaw('CAST(strftime(\'%m\', start_date) AS INTEGER)')
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->month)
                : $monthlyQuery
                    ->selectRaw('MONTH(start_date) as month, count(*) as total')
                    ->groupBy('month')
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->month);
        } else {
            $monthlyQuery->whereYear('created_at', $trendYear);
            $monthlyTotals = $isSqlite
                ? $monthlyQuery
                    ->selectRaw('CAST(strftime(\'%m\', created_at) AS INTEGER) as month, count(*) as total')
                    ->groupByRaw('CAST(strftime(\'%m\', created_at) AS INTEGER)')
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->month)
                : $monthlyQuery
                    ->selectRaw('MONTH(created_at) as month, count(*) as total')
                    ->groupBy('month')
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->month);
        }

        $monthlyTrend = collect(range(1, 12))->map(function (int $month) use ($trendYear, $monthlyTotals) {
            $row = $monthlyTotals->get($month);

            return [
                'label' => Carbon::create($trendYear, $month, 1)->format('M Y'),
                'count' => (int) ($row->total ?? 0),
            ];
        });

        $engagement = $this->buildEngagementStats($dateFrom, $dateTo, $colleges);

        return [
            'totalResearch' => $totalResearch,
            'researchInProgress' => $researchInProgress,
            'pendingApprovals' => $pendingApprovals,
            'scopusCount' => $scopusCount,
            'researchByCollege' => $researchByCollege,
            'collegeBreakdown' => $collegeBreakdown,
            'scopusByCollege' => $scopusByCollege,
            'presentedByCollege' => $presentedByCollege,
            'classificationBreakdown' => $classificationBreakdown,
            'workflowStatus' => $workflowStatus,
            'monthlyTrend' => $monthlyTrend,
            'submissionTrend' => $this->buildSubmissionTrend(),
            'engagedTotal' => $engagement['total'],
            'engagedByCollege' => $engagement['byCollege'],
        ];
    }

    private function resolveSelectedCollege(string $term): ?College
    {
        if ($term === '') {
            return null;
        }

        $active = College::query()->where('is_active', true);

        $byCode = (clone $active)->where('code', $term)->first();
        if ($byCode) {
            return $byCode;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';
        $matches = (clone $active)
            ->where(function (Builder $q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like);
            })
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{code: string, name: string, count: int}>
     */
    private function buildProgramBreakdown(?College $selectedCollege, ?string $dateFrom, ?string $dateTo): \Illuminate\Support\Collection
    {
        if ($selectedCollege === null) {
            return collect();
        }

        return Program::query()
            ->where('college_id', $selectedCollege->id)
            ->orderBy('code')
            ->get()
            ->map(function (Program $program) use ($selectedCollege, $dateFrom, $dateTo) {
                $count = Research::query()
                    ->where('mother_college_id', $selectedCollege->id)
                    ->where(function (Builder $q) use ($program) {
                        $q->whereHas('primaryAuthor', fn ($u) => $u->where('program_id', $program->id))
                            ->orWhereHas('researchAuthors', fn ($a) => $a->where('program_id', $program->id));
                    })
                    ->reportEligible()
                    ->whereOvpriApprovedBetween($dateFrom, $dateTo)
                    ->count();

                return [
                    'code' => $program->code,
                    'name' => $program->name,
                    'count' => $count,
                ];
            })
            ->filter(fn (array $p) => $p['count'] > 0)
            ->values();
    }

    /**
     * Monthly submissions for the last three calendar years (excluding drafts).
     *
     * @return array<int, array{label: string, count: int, year: int}>
     */
    private function buildSubmissionTrend(): array
    {
        $startYear = (int) now()->year - 2;
        $endYear = (int) now()->year;
        $isSqlite = Research::query()->getConnection()->getDriverName() === 'sqlite';

        $query = Research::query()
            ->reportEligible()
            ->whereOvpriApprovedBetween(null, null)
            ->whereYear('created_at', '>=', $startYear)
            ->whereYear('created_at', '<=', $endYear);

        $rows = $isSqlite
            ? $query
                ->selectRaw("CAST(strftime('%Y', created_at) AS INTEGER) as year, CAST(strftime('%m', created_at) AS INTEGER) as month, count(*) as total")
                ->groupByRaw("CAST(strftime('%Y', created_at) AS INTEGER), CAST(strftime('%m', created_at) AS INTEGER)")
                ->get()
            : $query
                ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, count(*) as total')
                ->groupByRaw('YEAR(created_at), MONTH(created_at)')
                ->get();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row->year][(int) $row->month] = (int) $row->total;
        }

        $trend = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            for ($month = 1; $month <= 12; $month++) {
                $trend[] = [
                    'label' => Carbon::create($year, $month, 1)->format('M Y'),
                    'count' => $indexed[$year][$month] ?? 0,
                    'year' => $year,
                ];
            }
        }

        return $trend;
    }

    /**
     * Unique faculty/staff on research (linked authors + primary authors).
     *
     * @param  \Illuminate\Support\Collection<int, College>  $colleges
     * @return array{total: int, byCollege: \Illuminate\Support\Collection<int, array{code: string, name: string, count: int}>}
     */
    private function buildEngagementStats(?string $dateFrom, ?string $dateTo, $colleges): array
    {
        $ids = $this->engagedUserIds($dateFrom, $dateTo, null);
        $users = User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'college_id']);

        $byCollegeId = $users->groupBy('college_id')->map->count();

        $byCollege = $colleges->map(fn (College $college) => [
            'code' => $college->code,
            'name' => $college->name,
            'count' => (int) ($byCollegeId[$college->id] ?? 0),
        ])->filter(fn (array $c) => $c['count'] > 0)->values();

        return [
            'total' => $ids->count(),
            'byCollege' => $byCollege,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function engagedUserIds(?string $dateFrom, ?string $dateTo, ?int $collegeId): \Illuminate\Support\Collection
    {
        $constrainResearch = function (Builder $q) use ($dateFrom, $dateTo): void {
            $q->where('approval_stage', '!=', 'draft')
                ->when($dateFrom, fn ($r) => $r->whereDate('start_date', '>=', $dateFrom))
                ->when($dateTo, fn ($r) => $r->whereDate('start_date', '<=', $dateTo));
        };

        $constrainUser = function (Builder $q) use ($collegeId): void {
            $this->constrainFacultyOrStaff($q);
            if ($collegeId !== null) {
                $q->where('college_id', $collegeId);
            }
        };

        $fromAuthors = ResearchAuthor::query()
            ->whereNotNull('user_id')
            ->whereHas('user', $constrainUser)
            ->whereHas('research', $constrainResearch)
            ->pluck('user_id');

        $fromPrimary = Research::query()
            ->whereNotNull('primary_author_id')
            ->tap($constrainResearch)
            ->whereHas('primaryAuthor', $constrainUser)
            ->pluck('primary_author_id');

        return $fromAuthors->merge($fromPrimary)->unique()->values();
    }

    private function constrainFacultyOrStaff(Builder $query): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereIn('user_type', ['faculty', 'staff'])
                ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['faculty']));
        });
    }

    private function baseResearchQuery(?string $dateFrom, ?string $dateTo): Builder
    {
        $query = Research::query()
            ->whereNotIn('approval_stage', ['draft', 'rejected']);

        if ($dateFrom) {
            $query->whereDate('start_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('start_date', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * Completed research counted on dashboards and reports (OVPRI-approved, completed status).
     */
    private function reportEligibleResearchQuery(?string $dateFrom, ?string $dateTo): Builder
    {
        return Research::query()
            ->reportEligible()
            ->whereOvpriApprovedBetween($dateFrom, $dateTo);
    }

    /**
     * @param  array<int, string>  $sdgNames
     * @return \Illuminate\Support\Collection<int, array{sdg: int, label: string, count: int}>
     */
    private function buildSdgDistribution(?string $dateFrom, ?string $dateTo, array $sdgNames): \Illuminate\Support\Collection
    {
        $query = $this->reportEligibleResearchQuery($dateFrom, $dateTo)
            ->whereNotNull('sdg_tags');
        $allSdgTags = $query->pluck('sdg_tags');
        $sdgCounts = array_fill(1, 17, 0);

        foreach ($allSdgTags as $tags) {
            $arr = is_array($tags) ? $tags : json_decode($tags, true) ?? [];
            foreach ($arr as $sdg) {
                if (isset($sdgCounts[(int) $sdg])) {
                    $sdgCounts[(int) $sdg]++;
                }
            }
        }

        return collect($sdgCounts)
            ->filter(fn ($count) => $count > 0)
            ->map(fn ($count, $num) => [
                'sdg' => (int) $num,
                'label' => 'SDG '.$num.': '.$sdgNames[$num],
                'count' => $count,
            ])
            ->sortByDesc('count')
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{label: string, count: int}>
     */
    private function buildClassificationBreakdown(Builder $base): \Illuminate\Support\Collection
    {
        $primaryKeys = ['internally_funded', 'self_funded', 'externally_funded', 'thesis'];
        $raw = (clone $base)
            ->select('research_classification', DB::raw('count(*) as total'))
            ->groupBy('research_classification')
            ->pluck('total', 'research_classification');

        $merged = [];
        foreach ($primaryKeys as $key) {
            $merged[$key] = (int) ($raw[$key] ?? 0);
        }
        $otherTotal = (int) ($raw['other'] ?? 0);
        foreach ($raw as $key => $total) {
            if (! in_array($key, array_merge($primaryKeys, ['other']), true)) {
                $otherTotal += (int) $total;
            }
        }
        $merged['other'] = $otherTotal;

        return collect(array_merge($primaryKeys, ['other']))->map(fn (string $key) => [
            'label' => self::CLASSIFICATION_LABELS[$key] ?? $key,
            'count' => $merged[$key],
        ]);
    }

    public function review(Request $request, Research $research): View
    {
        $this->authorize('view', $research);

        $research = Research::query()
            ->with([
                'primaryAuthor.college',
                'primaryAuthor.program',
                'researchAuthors.college',
                'researchAuthors.program',
                'motherCollege',
                'documents',
                'approvals' => fn ($q) => $q->orderBy('created_at'),
                'approvals.approver',
            ])
            ->findOrFail($research->id);

        $user = $request->user();
        $isInstitutionalReviewer = $user->hasAnyRole(['ovpri_admin', 'cdaic_admin', 'super_admin']);

        $isActiveOvpriQueue = $research->approval_stage === 'ovpri_review';
        $hasOvpriHistory = $research->approvals
            ->where('approver_id', $user->id)
            ->where('stage', 'ovpri')
            ->isNotEmpty();

        abort_unless($isInstitutionalReviewer || $isActiveOvpriQueue || $hasOvpriHistory, 403);

        return view('ovpri.review', ['research' => $research]);
    }
}
