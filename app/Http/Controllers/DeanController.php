<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Research;
use App\Models\User;
use App\Services\ResearchReportingService;
use App\Support\ResearchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DeanController extends Controller
{
    private const IN_PROGRESS_STATUSES = ['proposal', 'ongoing'];

    public function __construct(
        private ResearchReportingService $reporting,
    ) {}

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

        $cacheKey = 'dean_stats_v4_'.auth()->id().'_'.($dateFrom ?? 'all').'_'.($dateTo ?? 'all').'_'.now()->format('Y-m-d');

        $cached = Cache::remember($cacheKey, 1800, function () use ($college, $collegeId, $dateFrom, $dateTo, $scopeAllColleges) {
            $base = $this->collegeResearchQuery($college, $dateFrom, $dateTo, $scopeAllColleges);
            $accepted = $this->acceptedReportingQuery($college, $dateFrom, $dateTo, $scopeAllColleges);
            $outcomeMetrics = clone $accepted;

            $totalResearch = (clone $accepted)->count();
            $researchInProgress = (clone $base)->whereIn('status', self::IN_PROGRESS_STATUSES)->count();

            $pendingEndorsement = (clone $base)
                ->whereIn('status', [ResearchStatus::INITIAL_DEAN_REVIEW, ResearchStatus::FINAL_DEAN_REVIEW])
                ->whereNotNull('submitted_at')
                ->count();

            $publishedCount = (clone $outcomeMetrics)
                ->withOutcomeCodes(config('kmsar.published_outcome_codes', []))
                ->count();

            $presentedCount = (clone $outcomeMetrics)
                ->withOutcomeCodes(config('kmsar.presented_outcome_codes', []))
                ->count();

            $scopusIndexedCount = (clone $outcomeMetrics)
                ->withOutcomeCodes(config('kmsar.scopus_outcome_code', 'published_scopus_isi'))
                ->count();

            $yearList = $this->chartYearList($dateFrom, $dateTo);
            $yearSql = $this->reporting->acceptedYearSql();

            $submissionsByYearCounts = (clone $accepted)
                ->whereNotNull('research_accepted_at')
                ->selectRaw("{$yearSql['select']}, COUNT(*) as total")
                ->groupByRaw($yearSql['group'])
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->year => (int) $row->total]);

            $publishedByYearCounts = (clone $outcomeMetrics)
                ->withOutcomeCodes(config('kmsar.published_outcome_codes', []))
                ->whereNotNull('research_accepted_at')
                ->selectRaw("{$yearSql['select']}, COUNT(*) as total")
                ->groupByRaw($yearSql['group'])
                ->get()
                ->mapWithKeys(fn ($row) => [(int) $row->year => (int) $row->total]);

            $presentedByYearCounts = (clone $outcomeMetrics)
                ->withOutcomeCodes(config('kmsar.presented_outcome_codes', []))
                ->whereNotNull('research_accepted_at')
                ->selectRaw("{$yearSql['select']}, COUNT(*) as total")
                ->groupByRaw($yearSql['group'])
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

            $agendaThemeBreakdown = $this->reporting->buildAgendaThemeDistribution(clone $accepted);

            return [
                'totalResearch' => $totalResearch,
                'researchInProgress' => $researchInProgress,
                'pendingEndorsement' => $pendingEndorsement,
                'publishedCount' => $publishedCount,
                'presentedCount' => $presentedCount,
                'scopusIndexedCount' => $scopusIndexedCount,
                'submissionsByYear' => $submissionsByYear,
                'publishedByYear' => $publishedByYear,
                'presentedByYear' => $presentedByYear,
                'facultyStats' => $facultyStats,
                'agendaThemeBreakdown' => $agendaThemeBreakdown,
            ];
        });

        return view('dean.dashboard', [
            'college' => $college,
            'totalResearch' => $cached['totalResearch'] ?? 0,
            'researchInProgress' => $cached['researchInProgress'] ?? 0,
            'pendingEndorsement' => $cached['pendingEndorsement'] ?? 0,
            'presentedCount' => $cached['presentedCount'] ?? 0,
            'scopusIndexedCount' => $cached['scopusIndexedCount'] ?? 0,
            'recentResearch' => $recentResearch,
            'submissionsByYear' => $cached['submissionsByYear'],
            'publishedByYear' => $cached['publishedByYear'],
            'presentedByYear' => $cached['presentedByYear'],
            'facultyStats' => $cached['facultyStats'],
            'agendaThemeBreakdown' => $cached['agendaThemeBreakdown'],
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'scopeAllColleges' => $scopeAllColleges,
        ]);
    }

    private function collegeResearchQuery(?College $college, ?string $dateFrom = null, ?string $dateTo = null, bool $allColleges = false): Builder
    {
        $q = Research::query()
            ->where('status', '!=', ResearchStatus::PROPOSAL)
            ->whereNotIn('status', [ResearchStatus::INITIAL_REJECTED, ResearchStatus::FINAL_REJECTED]);

        if ($allColleges) {
            // Super admin: university-wide, no college filter.
        } elseif ($college) {
            $q->where('mother_college_id', $college->id);
        } else {
            $q->whereRaw('1 = 0');
        }

        return $this->applyStartDateRange($q, $dateFrom, $dateTo);
    }

    private function acceptedReportingQuery(?College $college, ?string $dateFrom, ?string $dateTo, bool $allColleges = false): Builder
    {
        return $this->reporting->acceptedQuery(
            $college?->id,
            $dateFrom,
            $dateTo,
            $allColleges || $college !== null,
        );
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

    /**
     * @param  list<string>  $codes
     */
    private function researchHasAnyOutcomeCode(Research $research, array $codes): bool
    {
        return $research->outcomeClassifications
            ->pluck('code')
            ->intersect($codes)
            ->isNotEmpty();
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
        $publishedCodes = config('kmsar.published_outcome_codes', []);
        $presentedCodes = config('kmsar.presented_outcome_codes', []);
        $scopusCode = config('kmsar.scopus_outcome_code', 'published_scopus');

        $researchQuery = $this->reporting->acceptedQuery($collegeId, $dateFrom, $dateTo, true)
            ->where(function (Builder $b) use ($facultyIds) {
                $b->whereIn('primary_author_id', $facultyIds)
                    ->orWhereHas('researchAuthors', fn (Builder $a) => $a->whereIn('user_id', $facultyIds));
            });

        $allResearch = $researchQuery
            ->with(['researchAuthors:id,research_id,user_id', 'outcomeClassifications:id,code'])
            ->get(['id', 'primary_author_id', 'status', 'research_accepted_at']);

        $rows = [];
        foreach ($facultyUsers as $user) {
            $relevant = $allResearch->filter(function (Research $r) use ($user) {
                if ((int) $r->primary_author_id === (int) $user->id) {
                    return true;
                }

                return $r->researchAuthors->pluck('user_id')->contains($user->id);
            });

            $total = $relevant->count();
            $published = $relevant->filter(fn (Research $r) => $this->researchHasAnyOutcomeCode($r, $publishedCodes))->count();
            $presented = $relevant->filter(fn (Research $r) => $this->researchHasAnyOutcomeCode($r, $presentedCodes))->count();
            $scopus = $relevant->filter(fn (Research $r) => $this->researchHasAnyOutcomeCode($r, [$scopusCode]))->count();

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
