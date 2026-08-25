<?php

namespace App\Models;

use App\Support\ResearchStatus;
use App\Support\TextNormalizer;
use Database\Factories\ResearchFactory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

class Research extends Model implements AuditableContract
{
    use AuditableTrait;
    /** @use HasFactory<ResearchFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'research';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference_number',
        'registration_type',
        'title',
        'primary_author_id',
        'mother_college_id',
        'other_college_id',
        'research_classification',
        'research_classification_other',
        'funding_agency',
        'sdg_tags',
        'agenda_themes',
        'expected_output',
        'expected_output_other',
        'start_date',
        'estimated_completion_date',
        'status',
        'submitted_at',
        'research_registered_at',
        'research_accepted_at',
        'first_completed_at',
        'revision_count',
        'final_review_count',
        'is_scopus_indexed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sdg_tags' => 'array',
            'agenda_themes' => 'array',
            'expected_output' => 'array',
            'other_college_id' => 'array',
            'start_date' => 'date',
            'estimated_completion_date' => 'date',
            'submitted_at' => 'datetime',
            'research_registered_at' => 'datetime',
            'research_accepted_at' => 'datetime',
            'first_completed_at' => 'datetime',
            'revision_count' => 'integer',
            'final_review_count' => 'integer',
            'is_scopus_indexed' => 'boolean',
        ];
    }

    protected function fundingAgency(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function expectedOutputOther(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    public function reviewCycle(): ?string
    {
        return ResearchStatus::reviewCycle($this->status);
    }

    public function isFullyEditable(): bool
    {
        return ResearchStatus::isFullyEditable((string) $this->status);
    }

    public function isOutcomeEditable(): bool
    {
        return ResearchStatus::isOutcomeEditable((string) $this->status);
    }

    /**
     * @return list<int>
     */
    public function otherCollegeIds(): array
    {
        $value = $this->other_college_id;

        if (! is_array($value)) {
            return $value !== null && $value !== '' ? [(int) $value] : [];
        }

        return array_values(array_map('intval', $value));
    }

    public function getOtherCollegeAttribute(): ?College
    {
        $ids = $this->otherCollegeIds();

        if ($ids === []) {
            return null;
        }

        return College::query()->find($ids[0]);
    }

    /**
     * @return Collection<int, College>
     */
    public function otherColleges(): Collection
    {
        $ids = $this->otherCollegeIds();

        if ($ids === []) {
            return collect();
        }

        return College::query()->whereIn('id', $ids)->orderBy('code')->get();
    }

    public function motherCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'mother_college_id');
    }

    /**
     * Limit dean queue/report queries to records routed to the dean's college (mother college).
     */
    public function scopeForDeanCollege(Builder $query, User $dean): Builder
    {
        if ($dean->hasRole('super_admin')) {
            return $query;
        }

        return $query->where('mother_college_id', $dean->college_id);
    }

    public function primaryAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_author_id');
    }

    public function researchAuthors(): HasMany
    {
        return $this->hasMany(ResearchAuthor::class);
    }

    public function authors(): HasMany
    {
        return $this->hasMany(ResearchAuthor::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function outcomeClassifications(): BelongsToMany
    {
        return $this->belongsToMany(OutcomeClassification::class, 'research_outcome')
            ->withTimestamps()
            ->orderBy('outcome_classifications.sort_order');
    }

    public function latestOvpriApproval(): HasOne
    {
        return $this->hasOne(Approval::class)
            ->ofMany(['acted_at' => 'max', 'id' => 'max'], function (Builder $query) {
                $query->where('stage', 'ovpri')
                    ->where('action', 'approved');
            });
    }

    public function ovpriApprovedAt(): ?CarbonInterface
    {
        return $this->latestOvpriApproval?->acted_at;
    }

    public function scopeReportEligible(Builder $query, bool $includeRejected = false): Builder
    {
        if ($includeRejected) {
            return $query->reportingCompleted(true);
        }

        return $query->reportingAccepted();
    }

    /**
     * Institutional totals: final OVPRI acceptance only.
     */
    public function scopeReportingAccepted(Builder $query): Builder
    {
        return $query->where('status', ResearchStatus::RESEARCH_ACCEPTED);
    }

    /**
     * Research that counts as "completed" for dashboards and institutional totals:
     * at least one outcome classification on the pivot (not workflow status).
     */
    public function scopeReportingCompleted(Builder $query, bool $includeRejected = false): Builder
    {
        $query->where('status', '!=', ResearchStatus::PROPOSAL)
            ->whereHas('outcomeClassifications');

        if (! $includeRejected) {
            $query->where('status', '!=', ResearchStatus::INITIAL_REJECTED);
        }

        return $query;
    }

    public static function isInProgressStatus(?string $status): bool
    {
        return $status !== null
            && in_array($status, config('kmsar.in_progress_statuses'), true);
    }

    public function scopeDashboardInProgress(Builder $query, ?string $status = null): Builder
    {
        $query->where('status', ResearchStatus::ONGOING);

        if ($status !== null && $status !== ResearchStatus::ONGOING) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function scopeWhereStartDateBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if (filled($from)) {
            $query->whereDate('start_date', '>=', $from);
        }

        if (filled($to)) {
            $query->whereDate('start_date', '<=', $to);
        }

        return $query;
    }

    public function scopeWhereFirstCompletedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if (filled($from)) {
            $query->whereDate('first_completed_at', '>=', $from);
        }

        if (filled($to)) {
            $query->whereDate('first_completed_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @param  string|list<string>  $codes
     */
    public function scopeWithOutcomeCodes(Builder $query, string|array $codes): Builder
    {
        $codes = is_array($codes) ? $codes : [$codes];

        return $query->whereHas(
            'outcomeClassifications',
            fn (Builder $q) => $q->whereIn('code', $codes)
        );
    }

    public function scopeWhereResearchRegisteredBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if (filled($from)) {
            $query->whereDate('research_registered_at', '>=', $from);
        }

        if (filled($to)) {
            $query->whereDate('research_registered_at', '<=', $to);
        }

        return $query;
    }

    public function scopeWhereResearchAcceptedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if (filled($from)) {
            $query->whereDate('research_accepted_at', '>=', $from);
        }

        if (filled($to)) {
            $query->whereDate('research_accepted_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @deprecated Use scopeWhereResearchAcceptedBetween for final acceptance reporting.
     */
    public function scopeWhereOvpriApprovedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query->whereResearchAcceptedBetween($from, $to);
    }

    public function scopeInitialDeanQueue(Builder $query): Builder
    {
        return $query->where('status', ResearchStatus::INITIAL_DEAN_REVIEW);
    }

    public function scopeFinalDeanQueue(Builder $query): Builder
    {
        return $query->where('status', ResearchStatus::FINAL_DEAN_REVIEW);
    }

    public function scopeInitialOvpriQueue(Builder $query): Builder
    {
        return $query->where('status', ResearchStatus::INITIAL_OVPRI_REVIEW);
    }

    public function scopeFinalOvpriQueue(Builder $query): Builder
    {
        return $query->where('status', ResearchStatus::FINAL_OVPRI_REVIEW);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return list<string>
     */
    public function expectedOutputKeys(): array
    {
        $v = $this->expected_output;

        if (! is_array($v)) {
            return [];
        }

        return array_values(array_unique($v));
    }

    /**
     * @return list<string>
     */
    public function outcomeClassificationCodes(): array
    {
        if ($this->relationLoaded('outcomeClassifications')) {
            return $this->outcomeClassifications->pluck('code')->all();
        }

        return $this->outcomeClassifications()->pluck('code')->all();
    }
}
