<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\QuantityBreak;
use App\Models\Quotation;
use App\Models\ProductionStage;
use App\Models\Setting;
use App\Models\RushTier;
use App\Services\AuditService;
use App\Support\ProductionPipeline;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quotations over the simple costing model: products and quantities, priced at
 * what their materials cost.
 *
 * Prices come from the product, never from the form. Each line snapshots the
 * name and unit price it was quoted at, so repricing a material afterwards
 * changes future quotations and leaves saved ones exactly as the customer
 * received them.
 */
class QuotationController extends Controller
{
    public function index(Request $request)
    {
        return view('quotations.index', [
            'quotations' => Quotation::withCount('items')
                ->when($request->q, fn ($query, $term) => $query->where(fn ($x) => $x
                    ->where('number', 'like', "%{$term}%")
                    ->orWhere('customer_name', 'like', "%{$term}%")))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('quotations.create', [
            'quotation' => null,
            'products' => $this->quotableProducts(),
            'customers' => Customer::active()->orderBy('contact_name')->get(),
            'breaks' => $this->breaks(),
            'margin' => Setting::margin(),
            'rushTiers' => $this->rushTiers(),
            'validityDays' => (int) Setting::number('quotation_validity_days', 15),
        ]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);

        $quotation = DB::transaction(function () use ($data, $audit) {
            $customer = $this->resolveCustomer($data);

            $quotation = Quotation::create([
                'number' => Quotation::nextNumber(),
                'customer_id' => $customer?->id,
                // Snapshotted: renaming or deleting the customer later must not
                // rewrite who this quotation was addressed to.
                'customer_name' => $customer?->label() ?? $data['customer_name'],
                'customer_contact' => $customer?->phone ?? ($data['customer_contact'] ?? null),
                'price_basis' => 'retail',
                'deadline' => $data['deadline'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->settle($quotation, $data['items']);

            $audit->log('created', $quotation, [], $quotation->toArray());

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->number} saved.");
    }

    /**
     * The volume discounts, biggest quantity first, so the browser can preview
     * a line the same way the server prices it.
     *
     * @return \Illuminate\Support\Collection<int, array<string, float>>
     */
    private function breaks()
    {
        return QuantityBreak::orderByDesc('min_quantity')->get()
            ->map(fn (QuantityBreak $tier) => [
                'min' => (float) $tier->min_quantity,
                'discount' => (float) $tier->discount_percentage,
            ])
            ->values();
    }

    /**
     * The rush tiers, tightest deadline first, so the browser can preview the
     * fee the same way the server charges it.
     *
     * @return \Illuminate\Support\Collection<int, array<string, float>>
     */
    private function rushTiers()
    {
        return RushTier::orderBy('within_days')->get()
            ->map(fn (RushTier $tier) => [
                'days' => (int) $tier->within_days,
                'surcharge' => (float) $tier->surcharge_percentage,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'required_without:customer_id|nullable|string|max:255',
            'customer_contact' => 'nullable|string|max:255',
            'customer_company' => 'nullable|string|max:255',
            'deadline' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.artwork_width_cm' => 'nullable|numeric|min:0.01|max:1000',
            'items.*.artwork_height_cm' => 'nullable|numeric|min:0.01|max:1000',
            'items.*.print_location' => 'required|string|max:100',
            'items.*.additional_locations' => 'nullable|array|max:9',
            'items.*.additional_locations.*.location' => 'required|string|max:100',
            'items.*.additional_locations.*.width' => 'required|numeric|min:0.01|max:1000',
            'items.*.additional_locations.*.height' => 'required|numeric|min:0.01|max:1000',
        ]);
    }

    public function edit(Quotation $quotation)
    {
        return view('quotations.edit', [
            'quotation' => $quotation->load('items.additionalLocations'),
            'products' => $this->quotableProducts(),
            'customers' => Customer::active()->orderBy('contact_name')->get(),
            'breaks' => $this->breaks(),
            'margin' => Setting::margin(),
            'rushTiers' => $this->rushTiers(),
            'validityDays' => (int) Setting::number('quotation_validity_days', 15),
        ]);
    }

    /**
     * Re-save a quotation from the form.
     *
     * The lines are replaced rather than patched, and every one is priced
     * again at today's material costs - editing a quotation is re-quoting it,
     * not preserving a price the customer never saw.
     */
    public function update(Request $request, Quotation $quotation, AuditService $audit)
    {
        $data = $this->validated($request);
        $before = $quotation->toArray();

        DB::transaction(function () use ($data, $quotation, $audit, $before) {
            $customer = $this->resolveCustomer($data);

            $quotation->update([
                'customer_id' => $customer?->id,
                'customer_name' => $customer?->label() ?? $data['customer_name'],
                'customer_contact' => $customer?->phone ?? ($data['customer_contact'] ?? null),
                'deadline' => $data['deadline'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->settle($quotation, $data['items']);

            $audit->log('quotation_changed', $quotation, $before, $quotation->toArray());
        });

        return redirect()->route('quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->number} updated.");
    }

    /**
     * Start a new quotation from an existing one.
     *
     * The customer, the notes and the product lines carry over; the prices do
     * not. A repeat order is quoted at today's material costs, not at what the
     * same job happened to cost months ago.
     */
    public function duplicate(Quotation $quotation, AuditService $audit)
    {
        $quotation->load('items');

        $copy = DB::transaction(function () use ($quotation, $audit) {
            $copy = Quotation::create([
                'number' => Quotation::nextNumber(),
                'customer_id' => $quotation->customer_id,
                'customer_name' => $quotation->customer_name,
                'customer_contact' => $quotation->customer_contact,
                'price_basis' => $quotation->price_basis,
                'deadline' => $quotation->deadline,
                'notes' => $quotation->notes,
                'created_by' => auth()->id(),
            ]);

            $items = $quotation->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
                'artwork_width_cm' => $item->artwork_width_cm,
                'artwork_height_cm' => $item->artwork_height_cm,
                'print_location' => $item->print_location,
            ])->all();

            $this->settle($copy, $items);
            $audit->log('created', $copy, [], $copy->toArray());

            return $copy;
        });

        return redirect()->route('quotations.edit', $copy)
            ->with('success', "Quotation {$copy->number} started from {$quotation->number}. Prices are today's.");
    }

    /**
     * Price the lines, then charge the deadline.
     *
     * The rush fee is charged on the subtotal rather than folded into each unit
     * price: turnaround is not what a piece is worth, and a customer asked to
     * pay for a deadline should be able to see that is what they are paying
     * for. It is worked out from the deadline once, here, and snapshotted, so
     * a quotation read next month still says what it charged rather than
     * quietly becoming less urgent as its deadline approaches.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function settle(Quotation $quotation, array $items): void
    {
        $lines = $this->fillLines($quotation, $items);

        // Layout, the mockup, the client sample and releasing the order happen
        // once however many pieces are ordered. Charged once here rather than
        // spread over the pieces, so a run of ten is honestly dearer per piece
        // than a run of a hundred, and the customer can see why.
        $setupCost = ProductionStage::setupCost(
            $quotation->items()->pluck('print_type')->all()
        );
        $margin = Setting::margin();
        $setup = round($margin > 0 ? $setupCost / (1 - ($margin / 100)) : $setupCost, 2);

        $subtotal = round($lines + $setup, 2);

        $surcharge = RushTier::surchargeFor($this->daysUntil($quotation->deadline));
        $rush = round($subtotal * ($surcharge / 100), 2);

        $quotation->forceFill([
            // A price stands for as long as the shop says it does. Set here
            // rather than typed, and set again whenever the quotation is
            // priced: an edit re-quotes at today's costs, so it is today the
            // clock should run from.
            'valid_until' => $this->validUntil(),
            'setup_cost' => $setup,
            'subtotal' => $subtotal,
            'rush_percentage' => $surcharge,
            'rush_amount' => $rush,
            'total' => round($subtotal + $rush, 2),
        ])->save();
    }

    /**
     * The date this quotation stops standing, from the shop's validity days.
     *
     * A setting of nought or less would expire the quotation the moment it was
     * written, which is nobody's intention, so it is read as no expiry at all.
     */
    private function validUntil(): ?string
    {
        $days = (int) Setting::number('quotation_validity_days', 15);

        return $days > 0 ? today()->addDays($days)->toDateString() : null;
    }

    /**
     * Whole days from today to a deadline, or null when none was given.
     *
     * A deadline already past counts as nought days rather than a negative
     * number, so an overdue job is charged as urgently as a same-day one
     * instead of falling out of every tier.
     */
    private function daysUntil($deadline): ?int
    {
        if (blank($deadline)) {
            return null;
        }

        return (int) max(0, today()->diffInDays($deadline, false));
    }

    /**
     * Price every line from its product and write them onto the quotation.
     *
     * Prices are computed here and never read from the form, so what is stored
     * cannot be edited from the browser. Each line snapshots the name, the
     * cost and the price it was quoted at, so repricing a material afterwards
     * changes future quotations and leaves saved ones exactly as the customer
     * received them - and the quotation still knows what it earned.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return float  the total of the lines, before setup and rush
     */
    private function fillLines(Quotation $quotation, array $items): float
    {
        $total = 0.0;

        foreach (array_values($items) as $index => $line) {
            $product = Product::with('materials')->findOrFail($line['product_id']);

            $width = $line['artwork_width_cm'] ?? null;
            $height = $line['artwork_height_cm'] ?? null;
            $area = $width && $height ? (float) $width * (float) $height : null;
            $extraLocations = $line['additional_locations'] ?? [];
            if ($area !== null) {
                foreach ($extraLocations as $extra) {
                    $area += (float) $extra['width'] * (float) $extra['height'];
                }
            }

            $costing = $product->costing($area);

            // A film or vinyl is consumed by how big the print is, so without a
            // size that part of the product cannot be costed and the line would
            // come out short.
            if ($costing['missing_artwork']) {
                throw ValidationException::withMessages([
                    'items' => "\"{$product->name}\" is printed on material sold by area, so it needs an artwork width and height.",
                ]);
            }

            // A product with nothing in it costs nothing, whatever the shop
            // charges for labour. Checked before the work is added, so a
            // labour figure can never make an empty product look quotable.
            $materials = (float) $costing['retail'];
            if ($materials <= 0) {
                throw ValidationException::withMessages([
                    'items' => "\"{$product->name}\" has no priced materials, so it cannot be quoted yet.",
                ]);
            }

            // Without a print type nobody has said how the product is made, so
            // there is no telling which stages of the floor it will occupy.
            if (blank($product->print_type)) {
                throw ValidationException::withMessages([
                    'items' => "\"{$product->name}\" has no print type set, so the work it takes cannot be costed. Set one on the product.",
                ]);
            }

            $quantity = (float) $line['quantity'];

            // How the product is made is the product's own business. The line
            // keeps a copy so re-routing the product later cannot rewrite a
            // quotation the customer has already been given.
            $printType = $product->print_type;

            // Materials, then the per-piece stages this route runs through the
            // floor, then the margin. The once-per-job stages are not here:
            // they are charged once on the quotation as setup.
            $labour = ProductionStage::perPieceCost($printType);
            $cost = $materials + $labour;
            $margin = Setting::margin();
            $listPrice = $margin > 0 ? $cost / (1 - ($margin / 100)) : $cost;

            // Bigger orders earn a break off the list price. It can never cut
            // into the cost: a discount that would sell below what the job
            // costs is held at cost instead of quietly losing money.
            $discount = QuantityBreak::discountFor($quantity);
            $discounted = $listPrice * (1 - ($discount / 100));
            if ($discounted < $cost) {
                $discounted = $cost;
                $discount = $listPrice > 0 ? (1 - ($discounted / $listPrice)) * 100 : 0.0;
            }

            $unitPrice = round($discounted, 2);

            $lineTotal = round($unitPrice * $quantity, 2);
            $total += $lineTotal;

            $item = $quotation->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'print_type' => $printType,
                'quantity' => $quantity,
                'labour_cost' => round($labour, 2),
                'unit_cost' => round($cost, 4),
                'artwork_width_cm' => $width,
                'artwork_height_cm' => $height,
                'print_location' => $line['print_location'] ?? null,
                'unit_price' => $unitPrice,
                'discount_percentage' => round($discount, 4),
                'line_total' => $lineTotal,
                'sort_order' => $index,
            ]);

            foreach ($extraLocations as $locationIndex => $extra) {
                $item->additionalLocations()->create([
                    'location' => $extra['location'], 'width_cm' => $extra['width'],
                    'height_cm' => $extra['height'], 'sort_order' => $locationIndex,
                ]);
            }
        }

        return $total;
    }

    /**
     * The customer this quotation is for: picked from the list, or created from
     * the details typed beside it.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        if (blank($data['customer_name'] ?? null)) {
            return null;
        }

        $customer = Customer::create([
            'code' => Customer::nextCode(),
            'contact_name' => $data['customer_name'],
            'company_name' => $data['customer_company'] ?? null,
            'phone' => $data['customer_contact'] ?? null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);
        app(AuditService::class)->log('created', $customer, [], $customer->toArray());

        return $customer;
    }

    public function show(Quotation $quotation)
    {
        return view('quotations.show', ['quotation' => $quotation->load('items.additionalLocations', 'creator', 'customer', 'artworks.uploader')]);
    }

    public function pdf(Quotation $quotation)
    {
        $quotation->load('items');

        return Pdf::loadView('quotations.pdf', [
            'quotation' => $quotation,
            'settings' => \App\Models\Setting::pluck('value', 'key'),
        ])->setPaper('a4')->download($quotation->number.'.pdf');
    }

    /**
     * What one square centimetre of print adds, so the browser can preview a
     * line while a size is typed. Measured rather than guessed: cost at 1 cm2
     * minus the fixed part.
     */
    private function areaRate(Product $product, string $key = 'retail'): float
    {
        if (! $product->requiresArtworkSize()) {
            return 0.0;
        }

        $fixed = $product->costing(0.0)[$key];

        return round($product->costing(1.0)[$key] - $fixed, 6);
    }

    /**
     * Only products that actually have a price. One with no materials would
     * quote at zero, so it is left out of the picker entirely.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function quotableProducts()
    {
        return Product::with('materials')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) {
                $costing = $product->costing();

                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'bulk' => round($costing['bulk'], 2),
                    // What the materials alone cost, and what each square
                    // centimetre of print adds to them. Labour and margin are
                    // applied on top, so the preview can follow the minutes.
                    'materials' => round($costing['retail'], 2),
                    'materials_per_cm2' => $this->areaRate($product, 'retail'),
                    'print_type' => $product->print_type,
                    'print_type_label' => ProductionPipeline::label($product->print_type),
                    'labour' => round(ProductionStage::perPieceCost($product->print_type), 2),
                    'setup' => round(ProductionStage::setupCost([$product->print_type]), 2),
                    'needs_artwork' => $product->requiresArtworkSize(),
                ];
            })
            ->filter(fn (array $product) => filled($product['print_type'])
                && ($product['bulk'] > 0 || $product['materials'] > 0 || $product['needs_artwork']))
            ->values();
    }
}
