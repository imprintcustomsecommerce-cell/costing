<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A volume discount: from this quantity upwards, this much comes off the unit
 * price. Breaks are global rather than per product, so one table decides how
 * the shop rewards bigger orders.
 */
class QuantityBreak extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['min_quantity' => 'float', 'discount_percentage' => 'float'];
    }

    /**
     * The discount earned by an order of this size, as a percentage.
     *
     * The most generous break the quantity actually reaches. Read once per
     * request: a quotation asks this for every line.
     */
    public static function discountFor(float $quantity): float
    {
        if (! app()->bound('costing.quantity_breaks')) {
            app()->instance(
                'costing.quantity_breaks',
                self::orderByDesc('min_quantity')->get(['min_quantity', 'discount_percentage'])
            );
        }

        $break = app('costing.quantity_breaks')
            ->first(fn (self $tier) => $quantity >= $tier->min_quantity);

        $discount = (float) ($break->discount_percentage ?? 0);

        // A break that would give the order away is not a price, it is a bug in
        // the table. Anything outside nought to a hundred is ignored.
        return $discount > 0 && $discount < 100 ? $discount : 0.0;
    }
}
