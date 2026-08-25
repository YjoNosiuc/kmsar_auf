<?php

namespace App\Services;

use App\Models\Research;
use App\Support\ResearchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportGeneratorService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function ovpriReport(array $filters, ?int $limit = null): Collection
    {
        $query = $this->baseResearchQueryWithRelations();

        $this->applyOvpriFilters($query, $filters);

        $query->orderByDesc('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collegeReport(int $collegeId, array $filters, ?int $limit = null): Collection
    {
        $query = $this->baseResearchQueryWithRelations()
            ->where('mother_college_id', $collegeId);

        $this->applyCollegeFilters($query, $filters);

        $query->orderByDesc('created_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function ovpriReportCount(array $filters): int
    {
        $query = $this->baseResearchQueryWithRelations();

        $this->applyOvpriFilters($query, $filters);

        return $query->count();
    }

    public function collegeReportCount(int $collegeId, array $filters): int
    {
        $query = $this->baseResearchQueryWithRelations()
            ->where('mother_college_id', $collegeId);

        $this->applyCollegeFilters($query, $filters);

        return $query->count();
    }

    /**
     * Distinct mother colleges represented in the filtered OVPRI result set.
     *
     * @param  array<string, mixed>  $filters
     */
    public function ovpriDistinctCollegeCount(array $filters): int
    {
        $query = Research::query();
        $this->applyOvpriFilters($query, $filters);

        return (int) $query->clone()
            ->whereNotNull('mother_college_id')
            ->selectRaw('count(distinct mother_college_id) as c')
            ->value('c');
    }

    /**
     * Count of Scopus-indexed records in the filtered OVPRI result set.
     *
     * @param  array<string, mixed>  $filters
     */
    public function ovpriScopusCount(array $filters): int
    {
        $query = $this->baseResearchQueryWithRelations();
        $this->applyOvpriFilters($query, $filters);
        $query->withOutcomeCodes(config('kmsar.scopus_outcome_code', 'published_scopus'));

        return $query->count();
    }

    /**
     * Published outputs (indexed or non-indexed) in the filtered college result set.
     *
     * @param  array<string, mixed>  $filters
     */
    public function collegePublishedCount(int $collegeId, array $filters): int
    {
        $query = $this->baseResearchQueryWithRelations()
            ->where('mother_college_id', $collegeId)
            ->withOutcomeCodes(config('kmsar.published_outcome_codes', []));

        $this->applyCollegeFilters($query, $filters);

        return $query->count();
    }

    /**
     * Conference presentations (internal or external) in the filtered college result set.
     *
     * @param  array<string, mixed>  $filters
     */
    public function collegePresentedCount(int $collegeId, array $filters): int
    {
        $query = $this->baseResearchQueryWithRelations()
            ->where('mother_college_id', $collegeId)
            ->withOutcomeCodes(config('kmsar.presented_outcome_codes', []));

        $this->applyCollegeFilters($query, $filters);

        return $query->count();
    }

    public function reportDate(?\Carbon\CarbonInterface $date): string
    {
        return $date?->format('M d, Y') ?? '—';
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            ResearchStatus::PROPOSAL => __('Proposal'),
            ResearchStatus::ONGOING => __('Ongoing'),
            ResearchStatus::RESEARCH_ACCEPTED => __('Research accepted'),
            ResearchStatus::INITIAL_DEAN_REVIEW => __('Initial dean review'),
            ResearchStatus::INITIAL_OVPRI_REVIEW => __('Initial OVPRI review'),
            ResearchStatus::INITIAL_REJECTED => __('Initial returned'),
            ResearchStatus::FINAL_DEAN_REVIEW => __('Final dean review'),
            ResearchStatus::FINAL_OVPRI_REVIEW => __('Final OVPRI review'),
            ResearchStatus::FINAL_REJECTED => __('Final returned'),
            'completed_not_presented_submitted' => __('Completed Research but NOT Presented/Submitted'),
            'presented_conference_auf' => __('Presented in a conference inside AUF'),
            'presented_conference_outside_auf' => __('Presented in a conference outside AUF (local/international)'),
            'published_non_scopus_wos' => __('Published in non-scopus/WoS indexed journals'),
            'submitted_scopus_isi' => __('Submitted in Scopus or ISI (Web of Science) indexed journals'),
            'accepted_scopus_isi' => __('Accepted in Scopus or ISI (Web of Science) indexed journals'),
            'submitted_patent_ipophl' => __('Submitted for patent application at IPOPHL'),
            'published_scopus_isi' => __('Published in Scopus or ISI (Web of Science) indexed journals'),
            'granted_patent_ipophl' => __('Granted Philippine Patent by IPOPHL'),
            default => str_replace('_', ' ', $status),
        };
    }

    public function progressDisplay(Research $research): string
    {
        if ($research->status === ResearchStatus::RESEARCH_ACCEPTED) {
            $codes = $research->outcomeClassificationCodes();

            if ($codes === []) {
                return $this->statusLabel(ResearchStatus::RESEARCH_ACCEPTED);
            }

            return collect($codes)
                ->map(fn (string $code) => $this->statusLabel($code))
                ->implode('; ');
        }

        return $this->statusLabel((string) $research->status);
    }

    public function registrationTypeLabel(?string $registrationType): string
    {
        return match ($registrationType) {
            'new' => __('New registration'),
            'existing' => __('Existing research'),
            default => '—',
        };
    }

    public function classificationLabel(?string $classification): string
    {
        if (! $classification) {
            return '—';
        }

        $labels = config('kmsar.research_classifications', []);

        return $labels[$classification] ?? str_replace('_', ' ', $classification);
    }

    /**
     * Free-text column for venue / output context (no dedicated DB column).
     */
    public function journalConferencePresentation(Research $research): string
    {
        $codes = $research->relationLoaded('outcomeClassifications')
            ? $research->outcomeClassifications->pluck('code')->all()
            : $research->outcomeClassificationCodes();

        if (array_intersect($codes, config('kmsar.published_outcome_codes', [])) !== []) {
            return __('Journal publication');
        }

        if (array_intersect($codes, config('kmsar.presented_outcome_codes', [])) !== []) {
            return __('Conference presentation');
        }

        if (in_array('patent_granted', $codes, true)) {
            return __('Patent');
        }

        $firstExpected = $research->expectedOutputKeys()[0] ?? null;

        return match ($firstExpected) {
            'publication' => __('Publication (expected)'),
            'patent' => __('Patent (expected)'),
            'policy_brief' => __('Policy brief (expected)'),
            'other' => __('Other (expected)'),
            default => '—',
        };
    }

    public function coAuthorsLine(Research $research): string
    {
        $names = $research->researchAuthors
            ->filter(fn ($author) => ! $author->is_primary)
            ->pluck('name')
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return '—';
        }

        return $names->implode('; ');
    }

    /**
     * Non-primary authors from research_authors, comma-separated (College Summary Report).
     */
    public function coAuthorsCommaSeparated(Research $research): string
    {
        $names = $research->researchAuthors
            ->filter(fn ($author) => ! $author->is_primary)
            ->pluck('name')
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return '—';
        }

        return $names->implode(', ');
    }

    public function otherCollegeAffiliations(Research $research): string
    {
        if ($research->otherCollege) {
            $code = $research->otherCollege->code ?? '';
            $name = $research->otherCollege->name ?? '';

            return trim($code.' — '.$name);
        }

        return '—';
    }

    /**
     * @return list<string>
     */
    public function filterSummaryLines(array $filters, bool $collegeReport, ?int $collegeId = null): array
    {
        $lines = [];

        if ($collegeReport && $collegeId !== null) {
            $lines[] = __('Mother college ID: :id', ['id' => $collegeId]);
        }

        if (! empty($filters['college_id']) && ! $collegeReport) {
            $lines[] = __('College ID: :id', ['id' => $filters['college_id']]);
        }

        if (! empty($filters['registration_type'])) {
            $lines[] = __('Registration type: :t', ['t' => $this->registrationTypeLabel((string) $filters['registration_type'])]);
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $lines[] = __('OVPRI approved: :from — :to', [
                'from' => $filters['date_from'] ?? '—',
                'to' => $filters['date_to'] ?? '—',
            ]);
        }

        if (! empty($filters['research_classification'])) {
            $lines[] = __('Classification: :c', ['c' => $this->classificationLabel((string) $filters['research_classification'])]);
        }

        if (! empty($filters['status'])) {
            $lines[] = __('Progress status: :s', ['s' => $this->statusLabel((string) $filters['status'])]);
        }

        if (! empty($filters['faculty'])) {
            $lines[] = __('Primary author ID: :id', ['id' => $filters['faculty']]);
        }

        return $lines;
    }

    protected function baseResearchQueryWithRelations(): Builder
    {
        return Research::query()->with([
            'primaryAuthor',
            'motherCollege',
            'researchAuthors',
            'latestOvpriApproval',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyOvpriFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['college_id'])) {
            $query->where('mother_college_id', (int) $filters['college_id']);
        }

        $this->applySharedFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyCollegeFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['faculty'])) {
            $query->where('primary_author_id', (int) $filters['faculty']);
        }

        $this->applySharedFilters($query, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applySharedFilters(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? null;
        $includeRejected = ($filters['include_rejected'] ?? '0') === '1';
        $inProgressFilter = Research::isInProgressStatus($status);

        if ($inProgressFilter) {
            $query->where('status', $status);

            if (! $includeRejected) {
                $query->whereNotIn('status', [
                    ResearchStatus::INITIAL_REJECTED,
                    ResearchStatus::FINAL_REJECTED,
                ]);
            }

            $query->whereResearchRegisteredBetween($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        } else {
            $query->reportingCompleted($includeRejected);

            if (filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null)) {
                $query->whereFirstCompletedBetween(
                    $filters['date_from'] ?? null,
                    $filters['date_to'] ?? null
                );
            }
        }

        if (! empty($filters['research_classification'])) {
            $query->where('research_classification', $filters['research_classification']);
        }

        if (! $inProgressFilter && ! empty($status)) {
            if (in_array($status, config('kmsar.completed_statuses', []), true)) {
                $query->whereHas('outcomeClassifications', fn (Builder $q) => $q->where('code', $status));
            } else {
                $query->where('status', $status);
            }
        }
    }
}
