<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Artwork extends Model
{
    protected $guarded = ['id'];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Whether the file is still on disk, which a stale row cannot assume. */
    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->stored_path);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        return $bytes < 1048576
            ? round($bytes / 1024, 1).' KB'
            : round($bytes / 1048576, 2).' MB';
    }
}
