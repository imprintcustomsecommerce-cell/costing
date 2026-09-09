<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItemLocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['width_cm' => 'decimal:2', 'height_cm' => 'decimal:2'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class, 'quotation_item_id');
    }
}
