<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('material_category_id')->nullable()->constrained()->nullOnDelete();
            // piece|meter|square_meter|gram|kilogram|sheet|square_cm|linear_meter|set
            $table->string('unit');
            $table->string('supplier')->nullable();
            // Denormalised cache of the currently effective cost. Source of truth is
            // material_cost_histories; never write this column directly.
            $table->decimal('current_cost', 12, 4)->default(0);
            $table->decimal('waste_percentage', 7, 4)->default(0); // 8.0000 = 8%
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });

        // Every cost change appends a row here. Rows are never overwritten so that
        // historical quotes can always resolve the cost that applied on their date.
        Schema::create('material_cost_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost', 12, 4);
            $table->decimal('previous_cost', 12, 4)->nullable();
            $table->decimal('change_percentage', 9, 4)->nullable();
            $table->date('effective_from');
            $table->string('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_cost_histories');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('material_categories');
    }
};
