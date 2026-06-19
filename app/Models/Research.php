<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Database\Factories\ResearchFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'funding_agency',
        'sdg_tags',
        'expected_output',
        'expected_output_other',
        'start_date',
        'estimated_completion_date',
        'status',
        'approval_stage',
        'submitted_at',
        'revision_count',
        'is_scopus_indexed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sdg_tags' => 'array',
            'expected_output' => 'array',
            'other_college_id' => 'array',
            'start_date' => 'date',
            'estimated_completion_date' => 'date',
            'submitted_at' => 'datetime',
            'revision_count' => 'integer',
            'is_scopus_indexed' => 'boolean',
        ];
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upper($value),
        );
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

    public function motherCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'mother_college_id');
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
}
