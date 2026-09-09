<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2',
            'artwork_width_cm' => 'decimal:2', 'artwork_height_cm' => 'decimal:2',
        ];
    }

    /** The printed area this line was costed at, if a size was given. */
    public function artworkAreaCm2(): ?float
    {
        return $this->artwork_width_cm && $this->artwork_height_cm
            ? (float) $this->artwork_width_cm * (float) $this->artwork_height_cm
            : null;
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Null once the product is deleted; the line keeps its own name and price. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function additionalLocations(): HasMany
    {
        return $this->hasMany(QuotationItemLocation::class)->orderBy('sort_order');
    }
}
