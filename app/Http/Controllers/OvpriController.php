<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Research;
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

    public function dashboard(): View
    {
        $stats = Cache::remember('ovpri_stats_'.now()->format('Y-m-d-H'), 3600, function () {
            $totalResearch = Research::query()->count();

            $pendingApprovals = Research::query()
                ->whereIn('approval_stage', ['dean_review', 'ovpri_review'])
                ->count();

            $publishedCount = Research::query()
                ->whereIn('status', self::PUBLISHED_STATUSES)
                ->count();

            $scopusCount = Research::query()
                ->where(function ($q) {
                    $q->where('is_scopus_indexed', true)
                        ->orWhere('status', 'published_scopus');
                })
                ->count();

            $researchCountsByCollege = Research::query()
                ->selectRaw('mother_college_id, count(*) as total')
                ->groupBy('mother_college_id')
                ->pluck('total', 'mother_college_id');

            $scopusCountsByCollege = Research::query()
                ->where('is_scopus_indexed', true)
                ->selectRaw('mother_college_id, count(*) as total')
                ->groupBy('mother_college_id')
                ->pluck('total', 'mother_college_id');

            $presentedCountsByCollege = Research::query()
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
                'count' => (int) ($researchCountsByCollege[$c->id] ?? 0),
            ]);

            $scopusByCollege = $colleges->map(fn (College $c) => [
                'label' => $c->code,
                'count' => (int) ($scopusCountsByCollege[$c->id] ?? 0),
            ]);

            $presentedByCollege = $colleges->map(fn (College $c) => [
                'label' => $c->code,
                'count' => (int) ($presentedCountsByCollege[$c->id] ?? 0),
            ]);

            $classificationBreakdown = $this->buildClassificationBreakdown();

            $year = (int) now()->year;
            $isSqlite = Research::query()->getConnection()->getDriverName() === 'sqlite';
            $monthlyTotals = $isSqlite
                ? Research::query()
                    ->selectRaw('CAST(strftime(\'%m\', created_at) AS INTEGER) as month, count(*) as total')
                    ->whereYear('created_at', $year)
                    ->groupByRaw('CAST(strftime(\'%m\', created_at) AS INTEGER)')
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->month)
                : Research::query()
                    ->selectRaw('MONTH(created_at) as month, count(*) as total')
                    ->whereYear('created_at', $year)
                    ->groupBy('month')
                    ->get()
                    ->keyBy(fn ($row) => (int) $row->month);

            $monthlyTrend = collect(range(1, 12))->map(function (int $month) use ($year, $monthlyTotals) {
                $row = $monthlyTotals->get($month);

                return [
                    'label' => Carbon::create($year, $month, 1)->format('M Y'),
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
                'monthlyTrend' => $monthlyTrend,
            ];
        });

        $sdgNames = [
            1 => 'No Poverty', 2 => 'Zero Hunger', 3 => 'Good Health',
            4 => 'Quality Education', 5 => 'Gender Equality', 6 => 'Clean Water',
            7 => 'Clean Energy', 8 => 'Decent Work', 9 => 'Innovation',
            10 => 'Reduced Inequalities', 11 => 'Sustainable Cities', 12 => 'Responsible Consumption',
            13 => 'Climate Action', 14 => 'Life Below Water', 15 => 'Life on Land',
            16 => 'Peace & Justice', 17 => 'Partnerships',
        ];

        $sdgDistribution = Cache::remember('sdg_counts', 3600, function () use ($sdgNames) {
            $allSdgTags = Research::whereNotNull('sdg_tags')->pluck('sdg_tags');
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
                ->values();
        });

        return view('ovpri.dashboard', [
            'totalResearch' => $stats['totalResearch'],
            'pendingApprovals' => $stats['pendingApprovals'],
            'publishedCount' => $stats['publishedCount'],
            'scopusCount' => $stats['scopusCount'],
            'researchByCollege' => $stats['researchByCollege'],
            'scopusByCollege' => $stats['scopusByCollege'],
            'presentedByCollege' => $stats['presentedByCollege'],
            'classificationBreakdown' => $stats['classificationBreakdown'],
            'sdgDistribution' => $sdgDistribution,
            'monthlyTrend' => $stats['monthlyTrend'],
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{label: string, count: int}>
     */
    private function buildClassificationBreakdown(): \Illuminate\Support\Collection
    {
        $primaryKeys = ['internally_funded', 'self_funded', 'externally_funded', 'thesis'];
        $raw = Research::query()
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
                'otherCollege',
                'documents',
                'approvals' => fn ($q) => $q->orderBy('created_at'),
                'approvals.approver',
            ])
            ->findOrFail($research->id);

        $isActiveOvpriQueue = $research->approval_stage === 'ovpri_review';
        $hasOvpriHistory = $research->approvals
            ->where('approver_id', $request->user()->id)
            ->where('stage', 'ovpri')
            ->isNotEmpty();
        abort_unless($isActiveOvpriQueue || $hasOvpriHistory, 403);

        return view('ovpri.review', ['research' => $research]);
    }
}
