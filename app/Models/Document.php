<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'research_id',
        'uploaded_by',
        'original_filename',
        'stored_filename',
        'disk_path',
        'external_link',
        'mime_type',
        'file_size_bytes',
        'research_status_at_upload',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'version' => 'integer',
        ];
    }

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
