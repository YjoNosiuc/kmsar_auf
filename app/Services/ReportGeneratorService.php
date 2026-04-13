<?php

namespace App\Services;

use App\Models\Research;
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
        $query->where('status', 'published_scopus');

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
            ->whereIn('status', ['published_scopus', 'published_non_indexed']);

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
            ->whereIn('status', ['presented_internal', 'presented_external']);

        $this->applyCollegeFilters($query, $filters);

        return $query->count();
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'published_scopus' => 'Scopus / ISI Indexed',
            'published_non_indexed' => 'Published (Non-Indexed)',
            'presented_external' => 'Conference Presentation — External',
            'presented_internal' => 'Conference Presentation — Internal',
            'patent_granted' => 'Patent Granted',
            'patent_submitted' => 'Patent Filed',
            'completed_unpublished' => 'Completed (Unpublished)',
            'ongoing' => 'Ongoing',
            'proposal' => 'Proposal Stage',
            default => str_replace('_', ' ', $status),
        };
    }

    public function registrationTypeLabel(?string $registrationType): string
    {
        return match ($registrationType) {
            'new' => __('New registration'),
            'update' => __('Update'),
            default => '—',
        };
    }

    public function classificationLabel(?string $classification): string
    {
        return match ($classification) {
            'self_funded' => __('Self-funded'),
            'internally_funded' => __('Internally funded'),
            'externally_funded' => __('Externally funded'),
            'thesis' => __('Thesis / dissertation'),
            'collaboration' => __('Collaboration'),
            'other' => __('Other'),
            default => $classification ? (string) $classification : '—',
        };
    }

    /**
     * Free-text column for venue / output context (no dedicated DB column).
     */
    public function journalConferencePresentation(Research $research): string
    {
        if (in_array($research->status, ['published_scopus', 'published_non_indexed'], true)) {
            return __('Journal publication');
        }

        if (in_array($research->status, ['presented_internal', 'presented_external'], true)) {
            return __('Conference presentation');
        }

        if (in_array($research->status, ['patent_submitted', 'patent_granted'], true)) {
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
            $lines[] = __('Created: :from — :to', [
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
    }
}
