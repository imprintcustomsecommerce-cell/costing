<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Support\Facades\DB;

class MaterialCostService
{
    public function __construct(private AuditService $audit) {}

    public function change(Material $material, float $cost, string $effectiveFrom, ?string $reason): void
    {
        DB::transaction(function () use ($material, $cost, $effectiveFrom, $reason) {
            $previousHistory = $material->costHistories()
                ->whereDate('effective_from', '<=', $effectiveFrom)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->value('cost');
            // Older installations may have a populated current_cost before
            // cost history was introduced. Preserve that value as the first
            // history row's comparison point instead of reporting no prior
            // price.
            $previous = (float) ($previousHistory ?? $material->current_cost ?? 0);
            $material->costHistories()->create([
                'cost' => $cost, 'previous_cost' => $previous ?: null,
                'change_percentage' => $previous > 0 ? (($cost - $previous) / $previous) * 100 : null,
                'effective_from' => $effectiveFrom, 'reason' => $reason, 'changed_by' => auth()->id(),
            ]);
            if ($effectiveFrom <= now()->toDateString()) {
                $current = (float) $material->costOn(today());
                $material->forceFill(['current_cost' => $current])->save();
            }
            $this->audit->log('material_cost_changed', $material, ['cost' => $previous], ['cost' => $cost], $reason);
        });
    }
}
