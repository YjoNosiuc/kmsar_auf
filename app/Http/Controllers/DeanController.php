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
    private const PUBLISHED_STATUSES = ['published_non_indexed', 'published_scopus'];

    private const PRESENTED_STATUSES = ['presented_internal', 'presented_external'];

    public function dashboard(Request $request): View
    {
        $collegeId = auth()->user()->college_id;
        $academicYear = $request->filled('academic_year') ? $request->integer('academic_year') : null;
        $academicYearOptions = $this->academicYearOptions();

        $college = $collegeId
            ? College::query()->find($collegeId)
            : null;

        $base = $this->collegeResearchQuery($college, $academicYear);

        $recentResearch = (clone $base)
            ->with(['primaryAuthor'])
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        $cacheKey = 'dean_stats_'.auth()->id().'_'.($academicYear ?? 'all').'_'.now()->format('Y-m-d');

        $cached = Cache::remember($cacheKey, 1800, function () use ($college, $collegeId, $academicYear) {
            $base = $this->collegeResearchQuery($college, $academicYear);

            $totalResearch = (clone $base)->count();

            $pendingEndorsement = (clone $base)
                ->where('approval_stage', 'dean_review')
                ->count();

            $publishedCount = (clone $base)
                ->whereIn('status', self::PUBLISHED_STATUSES)
                ->count();

            $scopusIndexedCount = (clone $base)
                ->where('is_scopus_indexed', true)
                ->count();

            $yearList = $academicYear !== null
                ? [$academicYear]
                : $this->lastNYears(5);

            $isSqlite = (clone $base)->getConnection()->getDriverName() === 'sqlite';
            $yearSelect = $isSqlite
                ? 'CAST(strftime(\'%Y\', start_date) AS INTEGER) as year'
                : 'YEAR(start_date) as year';
            $yearGroup = $isSqlite
                ? 'CAST(strftime(\'%Y\', start_date) AS INTEGER)'
                : 'YEAR(start_date)';

            $submissionsByYearCounts = (clone $base)
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
                ? $this->facultyResearchBreakdown((int) $collegeId, $academicYear)
                : [];

            return [
                'totalResearch' => $totalResearch,
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
            'pendingEndorsement' => $cached['pendingEndorsement'],
            'publishedCount' => $cached['publishedCount'],
            'scopusIndexedCount' => $cached['scopusIndexedCount'],
            'recentResearch' => $recentResearch,
            'submissionsByYear' => $cached['submissionsByYear'],
            'publishedByYear' => $cached['publishedByYear'],
            'presentedByYear' => $cached['presentedByYear'],
            'facultyStats' => $cached['facultyStats'],
            'academicYear' => $academicYear,
            'academicYearOptions' => $academicYearOptions,
        ]);
    }

    private function collegeResearchQuery(?College $college, ?int $academicYear = null): Builder
    {
        $q = Research::query();

        if ($college) {
            $q->where('mother_college_id', $college->id);
        } else {
            $q->whereRaw('1 = 0');
        }

        if ($academicYear !== null) {
            $q->whereYear('start_date', $academicYear);
        }

        return $q;
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
    private function academicYearOptions(): array
    {
        $current = (int) date('Y');

        return range($current, $current - 10);
    }

    /**
     * @return list<array{name: string, total: int, published: int, presented: int, scopus: int}>
     */
    private function facultyResearchBreakdown(int $collegeId, ?int $academicYear = null): array
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
            ->where('mother_college_id', $collegeId)
            ->where(function (Builder $b) use ($facultyIds) {
                $b->whereIn('primary_author_id', $facultyIds)
                    ->orWhereHas('researchAuthors', fn (Builder $a) => $a->whereIn('user_id', $facultyIds));
            });

        if ($academicYear !== null) {
            $researchQuery->whereYear('start_date', $academicYear);
        }

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

            $total = $relevant->count();
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
