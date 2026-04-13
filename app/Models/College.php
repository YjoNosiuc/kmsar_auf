<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Database\Factories\CollegeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    /** @use HasFactory<CollegeFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'head_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upper($value),
        );
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upper($value),
        );
    }

    public function headUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
