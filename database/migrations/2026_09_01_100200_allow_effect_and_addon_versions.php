<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Special effects and add-ons are effective-dated, but a UNIQUE index on `code`
 * made a second version impossible: raising 3D Puff from 80 to 95 forced either
 * an in-place edit (which would rewrite what historical quotes are explained
 * against) or a confusing duplicate code.
 *
 * Uniqueness moves to (code, effective_from): one version of a code per start
 * date, many versions over time. Historical quotes are unaffected because they
 * price from their saved calculation snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_effects', function (Blueprint $table) {
            $table->dropUnique('special_effects_code_unique');
            $table->unique(['code', 'effective_from'], 'special_effects_code_version_unique');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->dropUnique('addons_code_unique');
            $table->unique(['code', 'effective_from'], 'addons_code_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropUnique('addons_code_version_unique');
            $table->unique('code', 'addons_code_unique');
        });

        Schema::table('special_effects', function (Blueprint $table) {
            $table->dropUnique('special_effects_code_version_unique');
            $table->unique('code', 'special_effects_code_unique');
        });
    }
};
