<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'printing_cost' => 'decimal:4',
            'production_cost' => 'decimal:4',
            'sewing_cost' => 'decimal:4',
            'plastic_cost' => 'decimal:4',
            'box_cost' => 'decimal:4',
            'sticker_cost' => 'decimal:4',
            'design_count' => 'integer',
            'extra_design_cost' => 'decimal:4',
            'stitch_count' => 'integer',
            'cost_per_stitch' => 'decimal:6',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function defaultMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'default_material_id');
    }


    /**
     * How many square centimetres of print one unit of a material covers, or
     * null when it is not consumed by the size of the print. How a material is
     * measured is the material's own business.
     */
    private function areaDivisor(Material $material): ?float
    {
        return $material->areaDivisorCm2();
    }

    /** Whether any material is consumed by the size of the printed artwork. */
    public function requiresArtworkSize(): bool
    {
        return $this->materials->contains(
            fn (Material $material) => $this->areaDivisor($material) !== null
        );
    }

    /**
     * What one finished unit costs.
     *
     * New products use a four-part product breakdown: materials, printing,
     * labour and packaging. Legacy products keep the old behaviour until they
     * are saved through the new product form, so old automated integrations do
     * not suddenly double-charge the production-stage rates.
     *
     * Two kinds of material are costed differently:
     *
     * Counted, weighed or measured by length — a blank, a bag, thread. Its
     * quantity is fixed: one shirt is one shirt whatever is printed on it.
     *
     * Measured by area — transfer film, vinyl. What the product consumes
     * depends on how big the artwork is, so the quantity means "how many times
     * the printed area", almost always one. Without an artwork size these
     * cannot be costed at all, and the line says so rather than quietly
     * contributing nothing.
     *
     * Waste applies to both: a material with 8% waste needs 1.08 units bought
     * for every unit delivered — offcuts, misprints, the tail of a roll.
     *
     * @param  float|null  $artworkAreaCm2  the printed area, when one is known
     * @return array{lines: list<array<string, mixed>>, bulk: float, retail: float, difference: float, markup_percentage: float, missing_artwork: bool}
     */
    public function costing(?float $artworkAreaCm2 = null): array
    {
        $lines = [];
        $bulk = 0.0;
        $retail = 0.0;
        $missingArtwork = false;

        foreach ($this->materials as $material) {
            $quantity = (float) ($material->pivot->quantity ?? 1);
            $perUnit = $this->areaDivisor($material);
            $byArea = $perUnit !== null;

            if ($byArea && $artworkAreaCm2 === null) {
                $missingArtwork = true;
                $lines[] = $this->costLine($material, $quantity, 0.0, true);

                continue;
            }

            $used = $byArea
                ? $quantity * ($artworkAreaCm2 / $perUnit)
                : $quantity;

            $waste = (float) ($material->waste_percentage ?? 0);
            $consumed = $used * (1 + ($waste / 100));

            $line = $this->costLine($material, $quantity, $consumed, false, $byArea);
            $bulk += $line['bulk_total'];
            $retail += $line['retail_total'];
            $lines[] = $line;
        }

        $materialBulk = $bulk;
        $materialRetail = $retail;
        $components = $this->componentCosts();

        if ($this->usesCostBreakdown()) {
            $bulk += $components['total'];
            $retail += $components['total'];
        }

        $difference = $retail - $bulk;

        $result = [
            'lines' => $lines,
            'material_bulk' => $materialBulk,
            'material_retail' => $materialRetail,
            'components' => $components,
            'bulk' => $bulk,
            'retail' => $retail,
            'difference' => $difference,
            'markup_percentage' => $bulk > 0 ? ($difference / $bulk) * 100 : 0.0,
            'missing_artwork' => $missingArtwork,
        ];

        if ($this->usesCostBreakdown()) {
            $result += [
                'printing' => $components['printing'],
                'labour' => $components['labour'],
                'packaging' => $components['packaging'],
            ];
        }

        return $result;
    }

    public function usesCostBreakdown(): bool
    {
        return $this->costing_mode === 'product_breakdown';
    }

    /**
     * What printing one piece costs, the way its method is actually bought.
     *
     * Silkscreen is a screen per design: the base covers the first, and each
     * design after it adds the extra charge. Three designs at 80 and 10 is
     * 80 + 10 + 10.
     *
     * Embroidery is bought by the stitch: the base plus the stitch count at
     * the rate per stitch.
     *
     * Everything else - sublimation, DTF, eco solvent, vinyl - is a single
     * price for the piece, so the base stands on its own. A product that has
     * never had a design or stitch figure entered costs exactly its base,
     * whatever its method.
     */
    public function printingCost(): float
    {
        $base = (float) ($this->printing_cost ?? 0);

        return match ($this->print_type) {
            'silkscreen' => $base + max(0, (int) ($this->design_count ?? 1) - 1)
                * (float) ($this->extra_design_cost ?? 0),
            'embroidery' => $base + (int) ($this->stitch_count ?? 0)
                * (float) ($this->cost_per_stitch ?? 0),
            default => $base,
        };
    }

    /** @return array{printing: float, labour: float, packaging: float, total: float} */
    public function componentCosts(): array
    {
        $printing = $this->printingCost();
        $labour = $this->labourCost();
        $packaging = (float) ($this->plastic_cost ?? 0)
            + (float) ($this->box_cost ?? 0)
            + (float) ($this->sticker_cost ?? 0);

        return [
            'printing' => $printing,
            'labour' => $labour,
            'packaging' => $packaging,
            'total' => $printing + $labour + $packaging,
        ];
    }

    public function labourCost(): float
    {
        return (float) ($this->production_cost ?? 0) + (float) ($this->sewing_cost ?? 0);
    }

    /** @return array<string, mixed> */
    private function costLine(Material $material, float $quantity, float $consumed, bool $awaitingArtwork, bool $byArea = false): array
    {
        // Bulk is the supplier purchase cost per unit. MaterialCostService
        // keeps this cache in sync whenever an effective bulk cost is saved.
        $bulkUnit = (float) $material->current_cost;
        // A missing retail price falls back to bulk rather than zero, so a
        // half-filled catalogue reads as "same as bulk", not "free".
        $retailUnit = $material->retail_cost !== null ? (float) $material->retail_cost : $bulkUnit;

        return [
            'material' => $material,
            'quantity' => $quantity,
            'waste_percentage' => (float) ($material->waste_percentage ?? 0),
            'consumed' => $consumed,
            'by_area' => $byArea || $awaitingArtwork,
            'awaiting_artwork' => $awaitingArtwork,
            'bulk_unit' => $bulkUnit,
            'retail_unit' => $retailUnit,
            'bulk_total' => $consumed * $bulkUnit,
            'retail_total' => $consumed * $retailUnit,
            'estimated_retail' => $material->retail_cost === null,
        ];
    }

    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class)->withPivot('quantity')->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
