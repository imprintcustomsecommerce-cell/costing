<?php

namespace App\Models;

use App\Exceptions\PricingConfigurationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    public const UNITS = [
        'piece', 'meter', 'yard', 'linear_meter', 'square_meter', 'square_cm',
        'roll', 'sheet', 'gram', 'kilogram', 'pack', 'set',
    ];

    /**
     * Square centimetres in one unit of an area-measured material. Anything not
     * listed here is counted, weighed or measured by length, and one unit of it
     * means the same whatever size the artwork is.
     */
    private const AREA_UNITS = ['square_meter' => 10000, 'square_cm' => 1];

    /**
     * How many square centimetres of print one unit of this material covers, or
     * null when it is not consumed by the size of the print at all.
     *
     * Two ways a material can be area-measured. It is sold by area - film,
     * vinyl - and one unit covers a fixed number of square centimetres. Or it
     * is sold by the gram or millilitre but spent on the print - DTF ink,
     * adhesive powder - and its coverage says how much one square metre of
     * print consumes.
     */
    public function areaDivisorCm2(): ?float
    {
        if (isset(self::AREA_UNITS[$this->unit])) {
            return (float) self::AREA_UNITS[$this->unit];
        }

        $coverage = (float) ($this->coverage_per_sqm ?? 0);

        // Grams per square metre, inverted: how many square centimetres one
        // gram covers. A coverage of zero means the rate was never filled in,
        // so the material stays counted rather than costing nothing.
        return $coverage > 0 ? 10000 / $coverage : null;
    }

    protected $fillable = [
        'retail_cost',
        'sku',
        'name',
        'material_category_id',
        'unit',
        'width_cm',
        'length_cm',
        'coverage_per_sqm',
        'supplier',
        'waste_percentage',
        'is_active',
        'notes',
    ];

    /**
     * `current_cost` is deliberately not fillable. It is a denormalised cache of
     * the effective cost history row and is only ever written by
     * App\Services\Pricing\MaterialCostService.
     */
    protected function casts(): array
    {
        return [
            'current_cost' => 'decimal:4',
            'retail_cost' => 'decimal:4',
            'width_cm' => 'decimal:4',
            'length_cm' => 'decimal:4',
            'coverage_per_sqm' => 'decimal:4',
            'waste_percentage' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function costHistories(): HasMany
    {
        return $this->hasMany(MaterialCostHistory::class)->orderByDesc('effective_from');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'material_product')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The cost that applied on a given date, read from the immutable history.
     *
     * Throws rather than returning zero: a material with no cost on record is a
     * configuration gap that must reach an administrator, not a free material.
     */
    /**
     * The cost in force today, for display.
     *
     * `current_cost` is a cache that goes stale the moment a future-dated price
     * becomes effective, so admin screens resolve the history instead. Returns
     * null when the material has no cost recorded yet.
     */
    public function effectiveCostToday(): ?float
    {
        try {
            return (float) $this->costOn(today());
        } catch (PricingConfigurationException) {
            return null;
        }
    }

    /** A price already recorded that has not taken effect yet. */
    public function pendingCostChange(): ?MaterialCostHistory
    {
        return $this->costHistories()
            ->whereDate('effective_from', '>', today())
            ->orderBy('effective_from')
            ->first();
    }

    public function costOn(\DateTimeInterface|string $date): string
    {
        $history = $this->costHistories()
            ->whereDate('effective_from', '<=', $date)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if (! $history) {
            throw PricingConfigurationException::missing(
                'material cost',
                sprintf('%s (%s) has no cost effective on or before %s',
                    $this->name,
                    $this->sku,
                    $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date
                )
            );
        }

        return (string) $history->cost;
    }

    /**
     * Return the current cost in the unit used by DTF recipe consumption.
     *
     * A roll or sheet is priced by its usable cm²; all other materials use
     * their configured purchase unit (for example square metres of film or
     * grams of ink per printed cm²).
     */
    public function costPerRecipeUsageUnitOn(\DateTimeInterface|string $date): float
    {
        $cost = (float) $this->costOn($date);

        if (in_array($this->unit, ['roll', 'sheet'], true)) {
            $area = (float) $this->width_cm * (float) $this->length_cm;
            if ($area <= 0) {
                throw PricingConfigurationException::missing(
                    'DTF material dimensions',
                    "{$this->name} ({$this->sku}) needs roll/sheet width and length"
                );
            }

            return $cost / $area;
        }

        return $cost;
    }

    /** @deprecated DTF now uses the shared print-method recipe conversion. */
    public function costPerDtfUsageUnitOn(\DateTimeInterface|string $date): float
    {
        return $this->costPerRecipeUsageUnitOn($date);
    }
}
