<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Research;
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

    private const PUBLISHED_STATUSES = ['published_non_indexed', 'published_scopus'];

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
        $cacheSuffix = ($dateFrom ?? 'all').'_'.($dateTo ?? 'all');

        $stats = Cache::remember(
            'ovpri_stats_'.$cacheSuffix.'_'.now()->format('Y-m-d-H'),
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
            'sdg_counts_'.$cacheSuffix,
            3600,
            fn () => $this->buildSdgDistribution($dateFrom, $dateTo, $sdgNames)
        );

        return view('ovpri.dashboard', [
            'totalResearch' => $stats['totalResearch'],
            'pendingApprovals' => $stats['pendingApprovals'],
            'publishedCount' => $stats['publishedCount'],
            'scopusCount' => $stats['scopusCount'],
            'researchByCollege' => $stats['researchByCollege'],
            'scopusByCollege' => $stats['scopusByCollege'],
            'presentedByCollege' => $stats['presentedByCollege'],
            'classificationBreakdown' => $stats['classificationBreakdown'],
            'workflowStatus' => $stats['workflowStatus'],
            'sdgDistribution' => $sdgDistribution,
            'monthlyTrend' => $stats['monthlyTrend'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardStats(?string $dateFrom, ?string $dateTo): array
    {
        $base = $this->baseResearchQuery($dateFrom, $dateTo);

        $totalResearch = (clone $base)->count();

        $pendingApprovals = (clone $base)
            ->where('approval_stage', 'ovpri_review')
            ->count();

        $publishedCount = (clone $base)
            ->whereIn('status', self::PUBLISHED_STATUSES)
            ->count();

        $scopusCount = (clone $base)
            ->where(function ($q) {
                $q->where('is_scopus_indexed', true)
                    ->orWhere('status', 'published_scopus');
            })
            ->count();

        $researchCountsByCollege = (clone $base)
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
            'name' => $c->name,
            'count' => (int) ($researchCountsByCollege[$c->id] ?? 0),
        ]);

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

        $classificationBreakdown = $this->buildClassificationBreakdown($base);

        $workflowStatus = collect([
            ['key' => 'ovpri_review', 'label' => __('OVPRI Review'), 'count' => (clone $base)->where('approval_stage', 'ovpri_review')->count()],
            ['key' => 'approved', 'label' => __('Approved'), 'count' => (clone $base)->where('approval_stage', 'approved')->count()],
            ['key' => 'rejected', 'label' => __('Rejected'), 'count' => (clone $base)->where('approval_stage', 'rejected')->count()],
        ]);

        $trendYear = $dateFrom
            ? (int) date('Y', strtotime((string) $dateFrom))
            : (int) now()->year;
        $isSqlite = Research::query()->getConnection()->getDriverName() === 'sqlite';
        $monthlyQuery = clone $base;

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

        return [
            'totalResearch' => $totalResearch,
            'pendingApprovals' => $pendingApprovals,
            'publishedCount' => $publishedCount,
            'scopusCount' => $scopusCount,
            'researchByCollege' => $researchByCollege,
            'scopusByCollege' => $scopusByCollege,
            'presentedByCollege' => $presentedByCollege,
            'classificationBreakdown' => $classificationBreakdown,
            'workflowStatus' => $workflowStatus,
            'monthlyTrend' => $monthlyTrend,
        ];
    }

    private function baseResearchQuery(?string $dateFrom, ?string $dateTo): Builder
    {
        $query = Research::query()
            ->whereNotIn('approval_stage', ['draft', 'dean_review']);

        if ($dateFrom) {
            $query->whereDate('start_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('start_date', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $sdgNames
     * @return \Illuminate\Support\Collection<int, array{sdg: int, label: string, count: int}>
     */
    private function buildSdgDistribution(?string $dateFrom, ?string $dateTo, array $sdgNames): \Illuminate\Support\Collection
    {
        $query = $this->baseResearchQuery($dateFrom, $dateTo)->whereNotNull('sdg_tags');
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
