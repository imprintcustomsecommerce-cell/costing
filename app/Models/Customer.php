<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** What to show wherever the customer is named. */
    public function label(): string
    {
        return $this->company_name ?: $this->contact_name;
    }

    /** The next reference, shared so every screen numbers customers alike. */
    public static function nextCode(): string
    {
        return 'CUST-'.str_pad((string) ((static::max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT);
    }
}
