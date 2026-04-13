<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Program extends Model
{
    protected $fillable = [
        'college_id',
        'code',
        'name',
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

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }
}
