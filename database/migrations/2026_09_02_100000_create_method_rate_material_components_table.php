<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('method_rate_material_components', function (Blueprint $table) {
            $table->id();
            // A component belongs to one immutable rate version. This keeps a
            // material recipe independent for embroidery, silkscreen,
            // sublimation, stickers, or caps without duplicating five tables.
            $table->string('rate_type', 30);
            $table->unsignedBigInteger('rate_id');
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('charge_basis', 40);
            $table->decimal('consumption', 18, 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['rate_type', 'rate_id', 'material_id'], 'mrmc_rate_material_unique');
            $table->index(['rate_type', 'rate_id', 'is_active'], 'mrmc_rate_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('method_rate_material_components');
    }
};
