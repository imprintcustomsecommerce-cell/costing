<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Three gaps in the simple costing model.
     *
     * Labour: a product costed only from its materials charges nothing for the
     * person who presses it or the machine time it occupies, so the margin was
     * quietly paying for both. A product now says how many minutes of work one
     * unit takes, priced at an hourly rate kept in settings.
     *
     * Quantity breaks: a hundred shirts were priced at exactly a hundred times
     * one shirt. A break says that from a given quantity upwards, a percentage
     * comes off the unit price.
     *
     * Quotation lines snapshot the cost they were priced from, so a quotation
     * still knows what it earned after the materials behind it are repriced.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('labour_minutes', 10, 2)->nullable()->after('description');
        });

        Schema::create('quantity_breaks', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_quantity', 14, 4);
            $table->decimal('discount_percentage', 7, 4);
            $table->timestamps();
            $table->unique('min_quantity');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 4)->nullable()->after('quantity');
            $table->decimal('discount_percentage', 7, 4)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['unit_cost', 'discount_percentage']);
        });
        Schema::dropIfExists('quantity_breaks');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('labour_minutes');
        });
    }
};
