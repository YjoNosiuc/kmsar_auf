<?php

namespace App\Services;

use App\Models\OutcomeClassification;
use App\Models\Research;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ResearchReportingService
{
    /**
     * Institutional totals and chart base: status = research_accepted only.
     */
    public function acceptedQuery(
        ?int $motherCollegeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        bool $universityWide = true,
        bool $includeAffiliatedColleges = false,
    ): Builder {
        $query = Research::query()->reportingAccepted();

        if ($motherCollegeId !== null) {
            $query->forCollegeScope($motherCollegeId, $includeAffiliatedColleges);
        } elseif (! $universityWide) {
            $query->whereRaw('1 = 0');
        }

        return $query->whereResearchAcceptedBetween($dateFrom, $dateTo);
    }

    public function isSqlite(): bool
    {
        return Research::query()->getConnection()->getDriverName() === 'sqlite';
    }

    /**
     * @return array{select: string, group: string}
     */
    public function acceptedYearSql(): array
    {
        if ($this->isSqlite()) {
            return [
                'select' => "CAST(strftime('%Y', research_accepted_at) AS INTEGER) as year",
                'group' => "CAST(strftime('%Y', research_accepted_at) AS INTEGER)",
            ];
        }

        return [
            'select' => 'YEAR(research_accepted_at) as year',
            'group' => 'YEAR(research_accepted_at)',
        ];
    }

    /**
     * @return array{select: string, group: string}
     */
    public function acceptedMonthSql(): array
    {
        if ($this->isSqlite()) {
            return [
                'select' => "CAST(strftime('%m', research_accepted_at) AS INTEGER) as month",
                'group' => "CAST(strftime('%m', research_accepted_at) AS INTEGER)",
            ];
        }

        return [
            'select' => 'MONTH(research_accepted_at) as month',
            'group' => 'MONTH(research_accepted_at)',
        ];
    }

    /**
     * @return Collection<int, array{key: string, label: string, count: int}>
     */
    public function buildAgendaThemeDistribution(Builder $baseQuery): Collection
    {
        $themes = config('kmsar.agenda_themes', []);
        $counts = array_fill_keys(array_keys($themes), 0);

        (clone $baseQuery)
            ->whereNotNull('agenda_themes')
            ->get(['agenda_themes'])
            ->each(function (Research $research) use (&$counts): void {
                foreach ((array) $research->agenda_themes as $key) {
                    if (isset($counts[$key])) {
                        $counts[$key]++;
                    }
                }
            });

        return collect($themes)->map(fn (string $label, string $key) => [
            'key' => $key,
            'label' => $label,
            'count' => $counts[$key] ?? 0,
        ])->values();
    }

    /**
     * Mutually exclusive progress buckets for accepted research (highest outcome wins).
     *
     * @return Collection<int, array{code: string, label: string, count: int}>
     */
    public function buildResearchProgressDistribution(Builder $baseQuery): Collection
    {
        $classifications = OutcomeClassification::query()
            ->whereIn('code', config('kmsar.outcome_classification_codes', []))
            ->orderBy('sort_order')
            ->get(['code', 'name', 'sort_order']);

        $counts = array_fill_keys($classifications->pluck('code')->all(), 0);
        $noOutcomeKey = '_none';
        $counts[$noOutcomeKey] = 0;

        (clone $baseQuery)
            ->with(['outcomeClassifications:id,code,sort_order'])
            ->get(['id'])
            ->each(function (Research $research) use (&$counts, $noOutcomeKey): void {
                $outcomes = $research->outcomeClassifications;

                if ($outcomes->isEmpty()) {
                    $counts[$noOutcomeKey]++;

                    return;
                }

                $primary = $outcomes->sortByDesc('sort_order')->first();
                $code = $primary?->code;

                if ($code !== null && isset($counts[$code])) {
                    $counts[$code]++;
                }
            });

        $rows = $classifications->map(fn (OutcomeClassification $classification) => [
            'code' => $classification->code,
            'label' => $classification->name,
            'count' => $counts[$classification->code] ?? 0,
        ])->filter(fn (array $row) => $row['count'] > 0)->values();

        if ($counts[$noOutcomeKey] > 0) {
            $rows->push([
                'code' => $noOutcomeKey,
                'label' => __('Accepted — no outcome declared'),
                'count' => $counts[$noOutcomeKey],
            ]);
        }

        return $rows;
    }
}
