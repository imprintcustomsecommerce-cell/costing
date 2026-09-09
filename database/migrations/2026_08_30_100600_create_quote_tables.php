<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();            // QT-2026-000001
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Snapshot of the customer type at calculation time, because a
            // customer may be re-categorised later.
            $table->foreignId('customer_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('DRAFT');
            $table->foreignId('pricing_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Customer-facing money. All written by the pricing engine only.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_percentage', 7, 4)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_amount', 12, 2)->default(0);
            $table->decimal('tax_percentage', 7, 4)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Internal-only aggregates. Never serialised for staff.
            $table->decimal('production_cost', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->decimal('margin_percentage', 7, 4)->default(0);

            $table->string('currency', 8)->default('PHP');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'created_at']);
            $table->index('customer_id');
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('print_method_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('description')->nullable();
            // Method-specific specifications entered by staff (stitch counts,
            // sticker dimensions, cap type, cut type, ...). Specifications only,
            // never money.
            $table->json('specifications')->nullable();

            // Written exclusively by the pricing engine.
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('line_cost', 12, 2)->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('quote_id');
        });

        Schema::create('quote_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_item_id')->constrained()->cascadeOnDelete();
            $table->string('size_code');
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['quote_item_id', 'size_code'], 'qs_unique');
        });

        Schema::create('quote_print_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_location_id')->constrained()->restrictOnDelete();
            $table->decimal('width_cm', 9, 2)->nullable();
            $table->decimal('height_cm', 9, 2)->nullable();
            $table->unsignedInteger('colors')->nullable();       // silkscreen / embroidery
            $table->unsignedInteger('stitch_count')->nullable(); // embroidery
            $table->string('size_band')->nullable();             // silkscreen size multiplier
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('quote_item_id');
        });

        Schema::create('quote_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_print_location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('special_effect_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index('quote_item_id');
        });

        Schema::create('quote_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index('quote_item_id');
        });

        // Full internal breakdown plus the snapshot of every rate that was read.
        // One row per calculation run; the latest row is the current one.
        Schema::create('quote_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_version_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('material_cost', 12, 2)->default(0);
            $table->decimal('printing_cost', 12, 2)->default(0);
            $table->decimal('labor_cost', 12, 2)->default(0);
            $table->decimal('setup_cost', 12, 2)->default(0);
            $table->decimal('special_effect_cost', 12, 2)->default(0);
            $table->decimal('addon_cost', 12, 2)->default(0);
            $table->decimal('packaging_cost', 12, 2)->default(0);
            $table->decimal('size_surcharge_cost', 12, 2)->default(0);
            $table->decimal('location_cost', 12, 2)->default(0);
            $table->decimal('wastage_cost', 12, 2)->default(0);
            $table->decimal('overhead_cost', 12, 2)->default(0);
            $table->decimal('production_cost', 12, 2)->default(0);

            $table->string('pricing_model', 16)->default('margin'); // margin|markup
            $table->decimal('pricing_value', 7, 4)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('gross_profit', 12, 2)->default(0);
            $table->decimal('margin_percentage', 7, 4)->default(0);

            // Every rate value read during this calculation, so the quote can be
            // re-explained later even after the admin changes prices.
            $table->json('snapshot')->nullable();
            // Human readable trace of how the number was reached (admin only).
            $table->json('trace')->nullable();
            $table->timestamps();

            $table->index(['quote_id', 'quote_item_id'], 'qc_quote_item_idx');
        });

        Schema::create('quote_artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quote_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('print_location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('disk')->default('artwork');
            $table->string('mime_type', 128)->nullable();
            $table->string('extension', 16)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            // UPLOADED|FOR_REVIEW|REVISION_REQUIRED|APPROVED|FINAL
            $table->string('status')->default('UPLOADED');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['quote_id', 'version']);
            $table->index('status');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('quote_artworks');
        Schema::dropIfExists('quote_calculations');
        Schema::dropIfExists('quote_addons');
        Schema::dropIfExists('quote_effects');
        Schema::dropIfExists('quote_print_locations');
        Schema::dropIfExists('quote_sizes');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
    }
};
