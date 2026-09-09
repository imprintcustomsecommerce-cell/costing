<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductionStage;
use App\Models\QuantityBreak;
use App\Models\RushTier;
use App\Models\Setting;
use App\Services\AuditService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const FIELDS = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_website', 'quotation_validity_days', 'currency', 'tax_enabled', 'tax_rate', 'tax_inclusive', 'quotation_prefix', 'default_margin', 'minimum_gross_margin', 'default_labour_cost'];

    public function edit()
    {
        return view('admin.settings.edit', [
            'settings' => Setting::whereIn('key', self::FIELDS)->pluck('value', 'key'),
            'breaks' => QuantityBreak::orderBy('min_quantity')->get(),
            'rushTiers' => RushTier::orderBy('within_days')->get(),
            'stages' => ProductionStage::orderBy('sequence')->orderBy('label')->get(),
        ]);
    }

    public function update(Request $r, AuditService $audit)
    {
        $data = $r->validate(['company_name' => 'required|string|max:255', 'company_address' => 'nullable|string', 'company_phone' => 'nullable|string|max:50', 'company_email' => 'nullable|email', 'company_website' => 'nullable|url', 'quotation_validity_days' => 'required|integer|min:1|max:365', 'currency' => 'required|string|max:8', 'tax_enabled' => 'required|boolean', 'tax_rate' => 'required|numeric|min:0|max:100', 'tax_inclusive' => 'required|boolean', 'quotation_prefix' => 'required|string|max:10', 'default_margin' => 'nullable|numeric|min:0.01|max:99.99', 'minimum_gross_margin' => 'required|numeric|min:0|max:99.99', 'default_labour_cost' => 'nullable|numeric|min:0|max:1000000']);
        foreach (self::FIELDS as $key) {
            $setting = Setting::firstOrNew(['key' => $key]);
            $old = $setting->value;
            $setting->fill(['value' => (string) ($data[$key] ?? ''), 'type' => in_array($key, ['tax_enabled', 'tax_inclusive']) ? 'boolean' : (is_numeric($data[$key] ?? null) ? 'decimal' : 'string'), 'group' => 'company', 'label' => ucwords(str_replace('_', ' ', $key))])->save();
            if ($old !== $setting->value) {
                $audit->log('setting_changed', $setting, ['value' => $old], ['value' => $setting->value], $key);
            }
        }

        $this->syncBreaks($r, $audit);
        $this->syncRushTiers($r, $audit);
        $this->syncStageRates($r, $audit);

        return back()->with('success', 'System settings saved.');
    }

    /**
     * Save what each stage of the floor costs.
     *
     * The stages themselves come from the production pipeline and cannot be
     * added or removed here - only what they cost. A stage left blank costs
     * nothing, which is how a shop that does not run a stage prices it.
     */
    private function syncStageRates(Request $r, AuditService $audit): void
    {
        $rates = $r->validate([
            'stages' => 'nullable|array',
            'stages.*' => 'nullable|numeric|min:0|max:1000000',
        ])['stages'] ?? [];

        $before = ProductionStage::orderBy('key')->pluck('rate', 'key')->all();

        foreach ($rates as $key => $rate) {
            ProductionStage::where('key', $key)->update(['rate' => (float) ($rate ?: 0)]);
        }

        $after = ProductionStage::orderBy('key')->pluck('rate', 'key')->all();
        if ($before !== $after) {
            $audit->log('stage_rates_changed', new ProductionStage, $before, $after, 'Production stage rates');
        }
    }

    /**
     * Replace the rush fee table with the rows on the form.
     *
     * Rewritten rather than patched, for the same reason as the discounts: a
     * tier taken off the form must stop charging rather than linger.
     */
    private function syncRushTiers(Request $r, AuditService $audit): void
    {
        $rows = $r->validate([
            'rush' => 'nullable|array|max:20',
            'rush.*.within_days' => 'nullable|integer|min:0|max:365',
            'rush.*.surcharge_percentage' => 'nullable|numeric|min:0|max:100',
        ])['rush'] ?? [];

        $kept = collect($rows)
            // A row with no deadline is a blank line on the form, not a tier.
            ->filter(fn ($row) => filled($row['within_days'] ?? null))
            ->mapWithKeys(fn ($row) => [
                (string) (int) $row['within_days'] => (float) ($row['surcharge_percentage'] ?? 0),
            ]);

        $before = RushTier::orderBy('within_days')->pluck('surcharge_percentage', 'within_days')->all();

        RushTier::query()->delete();
        foreach ($kept as $days => $surcharge) {
            RushTier::create(['within_days' => (int) $days, 'surcharge_percentage' => $surcharge]);
        }

        $after = RushTier::orderBy('within_days')->pluck('surcharge_percentage', 'within_days')->all();
        if ($before !== $after) {
            $audit->log('rush_tiers_changed', new RushTier, $before, $after, 'Rush fees');
        }
    }

    /**
     * Replace the volume discount table with the rows on the form.
     *
     * Rows are rewritten rather than patched: the table is short, and a break
     * removed from the form must disappear rather than linger and keep
     * discounting.
     */
    private function syncBreaks(Request $r, AuditService $audit): void
    {
        $rows = $r->validate([
            'breaks' => 'nullable|array|max:20',
            'breaks.*.min_quantity' => 'nullable|numeric|min:1|max:1000000',
            'breaks.*.discount_percentage' => 'nullable|numeric|min:0|max:99.99',
        ])['breaks'] ?? [];

        $kept = collect($rows)
            // A row with no quantity is a blank line on the form, not a break.
            ->filter(fn ($row) => filled($row['min_quantity'] ?? null))
            ->mapWithKeys(fn ($row) => [
                (string) (float) $row['min_quantity'] => (float) ($row['discount_percentage'] ?? 0),
            ]);

        $before = QuantityBreak::orderBy('min_quantity')->pluck('discount_percentage', 'min_quantity')->all();

        QuantityBreak::query()->delete();
        foreach ($kept as $quantity => $discount) {
            QuantityBreak::create(['min_quantity' => (float) $quantity, 'discount_percentage' => $discount]);
        }

        $after = QuantityBreak::orderBy('min_quantity')->pluck('discount_percentage', 'min_quantity')->all();
        if ($before !== $after) {
            $audit->log('quantity_breaks_changed', new QuantityBreak, $before, $after, 'Volume discounts');
        }
    }
}
