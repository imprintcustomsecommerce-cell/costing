<?php

namespace App\Models;

use App\Support\ProductionPipeline;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * What one stage of the floor costs.
 *
 * The stages themselves come from the production pipeline and are fixed; only
 * the rates are the shop's to set.
 */
class ProductionStage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rate' => 'float', 'sequence' => 'integer'];
    }

    /**
     * Every stage rate, keyed by stage. Read once per request: a quotation
     * prices every line against the same table.
     *
     * @return Collection<string, float>
     */
    public static function rates(): Collection
    {
        if (! app()->bound('costing.stage_rates')) {
            app()->instance('costing.stage_rates', self::pluck('rate', 'key')->map(fn ($rate) => (float) $rate));
        }

        return app('costing.stage_rates');
    }

    /**
     * What one piece of this print type costs in work: every per-piece stage on
     * its route through the floor.
     *
     * A line naming no print type pays no labour rather than a guessed route -
     * an unrouted job is a job nobody has decided how to make yet.
     */
    public static function perPieceCost(?string $printType): float
    {
        return self::sumOf(ProductionPipeline::stagesOfBasis($printType, ProductionPipeline::PER_PIECE));
    }

    /**
     * What the once-per-job stages cost: layout, the mockup, the sample and
     * releasing the order.
     *
     * @param  array<int, string|null>  $printTypes  every route on the quotation
     */
    public static function setupCost(array $printTypes): float
    {
        // A quotation is laid out, sampled and released once, however many
        // routes it mixes, so the stages are unioned rather than added up per
        // line.
        $stages = collect($printTypes)
            ->flatMap(fn (?string $type) => ProductionPipeline::stagesOfBasis($type, ProductionPipeline::PER_JOB))
            ->unique()
            ->all();

        return self::sumOf($stages);
    }

    /** @param  array<int, string>  $stages */
    private static function sumOf(array $stages): float
    {
        $rates = self::rates();

        return collect($stages)->sum(fn (string $key) => (float) ($rates[$key] ?? 0));
    }

    /**
     * The stages of a route with what each costs, for showing a breakdown.
     *
     * @return array<int, array{key: string, label: string, basis: string, rate: float}>
     */
    public static function breakdown(?string $printType): array
    {
        $rates = self::rates();
        $stages = ProductionPipeline::stages();

        return collect(ProductionPipeline::stagesFor($printType))
            ->map(fn (string $key) => [
                'key' => $key,
                'label' => $stages[$key]['label'] ?? $key,
                'basis' => $stages[$key]['basis'] ?? ProductionPipeline::PER_PIECE,
                'rate' => (float) ($rates[$key] ?? 0),
            ])
            ->sortBy('sequence')
            ->values()
            ->all();
    }
}
