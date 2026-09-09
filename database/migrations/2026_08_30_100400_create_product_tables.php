<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('default_material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });

        // Which print methods may be quoted for this product.
        Schema::create('print_method_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('print_method_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'print_method_id'], 'pmp_unique');
        });

        // Which materials may be selected for this product.
        Schema::create('material_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_id', 'material_id'], 'mp_unique');
        });

        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('size_code');  // S, M, L, XL, 2XL
            $table->string('label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'size_code'], 'ps_unique');
        });

        // Bill of materials. One recipe per product (optionally per print method).
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Default');
            $table->foreignId('print_method_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });

        // A component of a recipe. `size_code` NULL means the quantity applies to
        // every size; a size-specific row overrides the generic one.
        Schema::create('product_recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('size_code')->nullable();
            $table->decimal('quantity', 12, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_recipe_id', 'size_code'], 'pri_recipe_size_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_recipe_items');
        Schema::dropIfExists('product_recipes');
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('material_product');
        Schema::dropIfExists('print_method_product');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
