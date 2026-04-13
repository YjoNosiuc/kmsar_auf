<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Database\Factories\ResearchAuthorFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchAuthor extends Model
{
    /** @use HasFactory<ResearchAuthorFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'research_id',
        'user_id',
        'author_type',
        'employee_number',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'name',
        'college_id',
        'college_text',
        'program',
        'program_id',
        'affiliated_college_id',
        'institution',
        'email',
        'is_primary',
        'can_edit',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'can_edit' => 'boolean',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upper($value),
        );
    }

    protected function firstName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function lastName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function middleName(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function suffix(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function employeeNumber(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function institution(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    protected function collegeText(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function affiliatedCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'affiliated_college_id');
    }
}
