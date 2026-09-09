<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thread colours bundled into the base embroidery price. Only colours beyond
 * this count are charged at cost_per_color.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('embroidery_rates', function (Blueprint $table) {
            // 0 keeps the previous behaviour: every colour is charged.
            $table->unsignedInteger('included_colors')->default(0)->after('cost_per_color');
        });
    }

    public function down(): void
    {
        Schema::table('embroidery_rates', function (Blueprint $table) {
            $table->dropColumn('included_colors');
        });
    }
};
