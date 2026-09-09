<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtf_rate_media_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dtf_rate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            // Quantity consumed to produce one square centimetre, measured in
            // the material's pricing unit (roll/sheet are converted to cm²).
            $table->decimal('consumption_per_cm2', 18, 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['dtf_rate_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtf_rate_media_components');
    }
};
