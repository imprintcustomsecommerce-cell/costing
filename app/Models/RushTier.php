<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A rush fee: a job wanted within this many days pays this much on top.
 *
 * Turnaround is not work content. A job wanted sooner does not take longer to
 * make - it displaces whatever else was on the press - so this is charged on
 * the quotation rather than folded into what a piece costs.
 */
class RushTier extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['within_days' => 'integer', 'surcharge_percentage' => 'float'];
    }

    /**
     * The surcharge earned by a deadline this many days away, as a percentage.
     *
     * The tightest tier the deadline falls inside. A deadline already past, or
     * today, is as urgent as it gets and takes the tightest tier there is.
     */
    public static function surchargeFor(?int $days): float
    {
        if ($days === null) {
            return 0.0;
        }

        if (! app()->bound('costing.rush_tiers')) {
            app()->instance(
                'costing.rush_tiers',
                self::orderBy('within_days')->get(['within_days', 'surcharge_percentage'])
            );
        }

        $tier = app('costing.rush_tiers')
            ->first(fn (self $tier) => $days <= $tier->within_days);

        $surcharge = (float) ($tier->surcharge_percentage ?? 0);

        // A surcharge outside nought to a hundred is a bug in the table, not a
        // price. Ignored rather than charged.
        return $surcharge > 0 && $surcharge <= 100 ? $surcharge : 0.0;
    }
}
