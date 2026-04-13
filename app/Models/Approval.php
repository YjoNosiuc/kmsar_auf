<?php

namespace App\Models;

use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'research_id',
        'approver_id',
        'stage',
        'action',
        'remarks',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    protected function remarks(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => TextNormalizer::upperNullable($value),
        );
    }

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
