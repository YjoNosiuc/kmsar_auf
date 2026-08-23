<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Research;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DeanController extends Controller
{
    private const COMPLETED_STATUSES = [
        'completed_unpublished',
        'presented_internal',
        'presented_external',
        'published_non_indexed',
        'published_scopus',
        'patent_granted',
    ];

    private const IN_PROGRESS_STATUSES = ['proposal', 'ongoing'];

    private const PUBLISHED_STATUSES = ['published_non_indexed', 'published_scopus'];

    private const PRESENTED_STATUSES = ['presented_internal', 'presented_external'];

    public function dashboard(Request $request): View
    {
        $user = $request->user();
        $scopeAllColleges = $user->hasRole('super_admin');
        $collegeId = $scopeAllColleges ? null : $user->college_id;
        $dateFrom = $request->filled('date_from') ? $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') ? $request->input('date_to') : null;

        $college = $collegeId
            ? College::query()->find($collegeId)
            : null;

        $base = $this->collegeResearchQuery($college, $dateFrom, $dateTo, $scopeAllColleges);

        $recentResearch = (clone $base)
            ->with(['primaryAuthor'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        $cacheKey = 'dean_stats_v2_'.auth()->id().'_'.($dateFrom ?? 'all').'_'.($dateTo ?? 'all').'_'.now()->format('Y-m-d');

        $cached = Cache::remember($cacheKey, 1800, function () use ($college, $collegeId, $dateFrom, $dateTo, $scopeAllColleges) {
            $base = $this->collegeResearchQuery($college, $dateFrom, $dateTo, $scopeAllColleges);
            $completed = (clone $base)->whereIn('status', self::COMPLETED_STATUSES);

            $totalResearch = (clone $completed)->count();
            $researchInProgress = (clone $base)->whereIn('status', self::IN_PROGRESS_STATUSES)->count();

            $pendingEndorsement = (clone $base)
                ->where('approval_stage', 'dean_review')
                ->count();

            $publishedCount = (clone $base)
                ->whereIn('status', self::PUBLISHED_STATUSES)
                ->count();

            $scopusIndexedCount = (clone $base)
                ->where('is_scopus_indexed', true)
                ->count();

            $yearList = $this->chartYearList($dateFrom, $dateTo);

            $isSqlite = (clone $base)->getConnection()->getDriverName() === 'sqlite';
            $yearSelect = $isSqlite
                ? 'CAST(strftime(\'%Y\', start_date) AS INTEGER) as year'
                : 'YEAR(start_date) as year';
            $yearGroup = $isSqlite
                ? 'CAST(strftime(\'%Y\', start_date) AS INTEGER)'
                : 'YEAR(start_date)';

            $submissionsByYearCounts = (clone $completed)
                ->selectRaw("{$yearSelect}, COUNT(*) as total")
                ->groupByRaw($yearGroup)
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->year => (int) $row->total]);

            $publishedByYearCounts = (clone $base)
                ->whereIn('status', self::PUBLISHED_STATUSES)
                ->selectRaw("{$yearSelect}, COUNT(*) as total")
                ->groupByRaw($yearGroup)
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->year => (int) $row->total]);

            $presentedByYearCounts = (clone $base)
                ->whereIn('status', self::PRESENTED_STATUSES)
                ->selectRaw("{$yearSelect}, COUNT(*) as total")
                ->groupByRaw($yearGroup)
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->year => (int) $row->total]);

            $submissionsByYear = collect($yearList)->map(function (int $year) use ($submissionsByYearCounts) {
                return [
                    'year' => (string) $year,
                    'count' => (int) ($submissionsByYearCounts[$year] ?? 0),
                ];
            });

            $publishedByYear = collect($yearList)->map(function (int $year) use ($publishedByYearCounts) {
                return [
                    'year' => (string) $year,
                    'count' => (int) ($publishedByYearCounts[$year] ?? 0),
                ];
            });

            $presentedByYear = collect($yearList)->map(function (int $year) use ($presentedByYearCounts) {
                return [
                    'year' => (string) $year,
                    'count' => (int) ($presentedByYearCounts[$year] ?? 0),
                ];
            });

            $facultyStats = $collegeId
                ? $this->facultyResearchBreakdown((int) $collegeId, $dateFrom, $dateTo)
                : [];

            return [
                'totalResearch' => $totalResearch,
                'researchInProgress' => $researchInProgress,
                'pendingEndorsement' => $pendingEndorsement,
                'publishedCount' => $publishedCount,
                'scopusIndexedCount' => $scopusIndexedCount,
                'submissionsByYear' => $submissionsByYear,
                'publishedByYear' => $publishedByYear,
                'presentedByYear' => $presentedByYear,
                'facultyStats' => $facultyStats,
            ];
        });

        return view('dean.dashboard', [
            'college' => $college,
            'totalResearch' => $cached['totalResearch'],
            'researchInProgress' => $cached['researchInProgress'],
            'pendingEndorsement' => $cached['pendingEndorsement'],
            'publishedCount' => $cached['publishedCount'],
            'scopusIndexedCount' => $cached['scopusIndexedCount'],
            'recentResearch' => $recentResearch,
            'submissionsByYear' => $cached['submissionsByYear'],
            'publishedByYear' => $cached['publishedByYear'],
            'presentedByYear' => $cached['presentedByYear'],
            'facultyStats' => $cached['facultyStats'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'scopeAllColleges' => $scopeAllColleges,
        ]);
    }

    private function collegeResearchQuery(?College $college, ?string $dateFrom = null, ?string $dateTo = null, bool $allColleges = false): Builder
    {
        $q = Research::query()
            ->whereNotIn('approval_stage', ['draft', 'rejected']);

        if ($allColleges) {
            // Super admin: university-wide, no college filter.
        } elseif ($college) {
            $q->where('mother_college_id', $college->id);
        } else {
            $q->whereRaw('1 = 0');
        }

        return $this->applyStartDateRange($q, $dateFrom, $dateTo);
    }

    /**
     * @return list<int>
     */
    private function lastNYears(int $n): array
    {
        $end = (int) date('Y');
        $start = $end - $n + 1;

        return range($start, $end);
    }

    /**
     * @return list<int>
     */
    private function chartYearList(?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom || $dateTo) {
            $start = $dateFrom ? (int) date('Y', strtotime((string) $dateFrom)) : (int) date('Y') - 4;
            $end = $dateTo ? (int) date('Y', strtotime((string) $dateTo)) : (int) date('Y');
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }

            return range($start, $end);
        }

        return $this->lastNYears(5);
    }

    private function applyStartDateRange(Builder $query, ?string $dateFrom, ?string $dateTo): Builder
    {
        if ($dateFrom) {
            $query->whereDate('start_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('start_date', '<=', $dateTo);
        }

        return $query;
    }

    /**
     * @return list<array{name: string, total: int, published: int, presented: int, scopus: int}>
     */
    private function facultyResearchBreakdown(int $collegeId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $facultyUsers = User::query()
            ->role('faculty')
            ->where('college_id', $collegeId)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($facultyUsers->isEmpty()) {
            return [];
        }

        $facultyIds = $facultyUsers->pluck('id')->all();

        $researchQuery = Research::query()
            ->whereNotIn('approval_stage', ['draft', 'rejected'])
            ->where('mother_college_id', $collegeId)
            ->where(function (Builder $b) use ($facultyIds) {
                $b->whereIn('primary_author_id', $facultyIds)
                    ->orWhereHas('researchAuthors', fn (Builder $a) => $a->whereIn('user_id', $facultyIds));
            });

        $this->applyStartDateRange($researchQuery, $dateFrom, $dateTo);

        $allResearch = $researchQuery
            ->with(['researchAuthors:id,research_id,user_id'])
            ->get(['id', 'primary_author_id', 'status', 'is_scopus_indexed']);

        $rows = [];
        foreach ($facultyUsers as $user) {
            $relevant = $allResearch->filter(function (Research $r) use ($user) {
                if ((int) $r->primary_author_id === (int) $user->id) {
                    return true;
                }

                return $r->researchAuthors->pluck('user_id')->contains($user->id);
            });

            $total = $relevant->whereIn('status', self::COMPLETED_STATUSES)->count();
            $published = $relevant->whereIn('status', self::PUBLISHED_STATUSES)->count();
            $presented = $relevant->whereIn('status', self::PRESENTED_STATUSES)->count();
            $scopus = $relevant->where('is_scopus_indexed', true)->count();

            $rows[] = [
                'name' => $user->name,
                'total' => $total,
                'published' => $published,
                'presented' => $presented,
                'scopus' => $scopus,
            ];
        }

        return $rows;
    }
}
