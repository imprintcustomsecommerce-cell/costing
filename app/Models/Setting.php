<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
    ];

    /**
     * A numeric setting, read at most once per request.
     *
     * A quotation prices every line against the same few numbers, and they
     * change only when somebody edits Settings, so re-reading them per line is
     * a query for nothing. Held on the container rather than a static, so a
     * value lasts exactly one request and does not survive into the next test.
     */
    public static function number(string $key, float $default = 0.0): float
    {
        $binding = 'costing.setting.'.$key;

        if (! app()->bound($binding)) {
            app()->instance($binding, (float) (self::where('key', $key)->value('value') ?? $default));
        }

        return (float) app($binding);
    }

    /**
     * The margin the business sells at, as a percentage of the selling price.
     * A margin of 100% or more would divide by zero or invert the price.
     */
    public static function margin(): float
    {
        $margin = self::number('default_margin');

        return $margin > 0 && $margin < 100 ? $margin : 0.0;
    }

    /**
     * The margin a quoted line may never be discounted below, as a percentage
     * of the selling price. Nought means the floor is simply cost.
     */
    public static function minimumMargin(): float
    {
        $floor = self::number('minimum_gross_margin');

        return $floor > 0 && $floor < 100 ? $floor : 0.0;
    }

    /** What the work on one piece costs, the same for every job in the shop. */
    public static function labourPerPiece(): float
    {
        return max(0.0, self::number('default_labour_cost'));
    }
}
