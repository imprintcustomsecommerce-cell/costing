<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    // total is derived from the lines, never posted.
    protected $guarded = ['id', 'total'];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            // When the customer needs the job, which decides the rush fee.
            'deadline' => 'date',
            'subtotal' => 'decimal:2',
            'rush_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /** Null once the customer is deleted; the snapshot on the row still names them. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The next number in sequence, e.g. QT-2026-000004. */
    public static function nextNumber(): string
    {
        $year = now()->format('Y');
        $count = static::where('number', 'like', "QT-{$year}-%")->count() + 1;

        return sprintf('QT-%s-%06d', $year, $count);
    }
}
