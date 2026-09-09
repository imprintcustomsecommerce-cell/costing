<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_effects', function (Blueprint $table) {
            $table->string('effect_code')->nullable()->after('special_effect_id');
            $table->string('effect_name')->nullable()->after('effect_code');
        });
        Schema::table('quote_addons', function (Blueprint $table) {
            $table->string('addon_code')->nullable()->after('addon_id');
            $table->string('addon_name')->nullable()->after('addon_code');
        });

        foreach (DB::table('quote_effects')
            ->join('special_effects', 'special_effects.id', '=', 'quote_effects.special_effect_id')
            ->select('quote_effects.id', 'special_effects.code', 'special_effects.name')->get() as $selection) {
            DB::table('quote_effects')->where('id', $selection->id)->update([
                'effect_code' => $selection->code,
                'effect_name' => $selection->name,
            ]);
        }
        foreach (DB::table('quote_addons')
            ->join('addons', 'addons.id', '=', 'quote_addons.addon_id')
            ->select('quote_addons.id', 'addons.code', 'addons.name')->get() as $selection) {
            DB::table('quote_addons')->where('id', $selection->id)->update([
                'addon_code' => $selection->code,
                'addon_name' => $selection->name,
            ]);
        }

        Schema::table('quote_effects', function (Blueprint $table) {
            $table->dropForeign(['special_effect_id']);
            $table->unsignedBigInteger('special_effect_id')->nullable()->change();
            $table->foreign('special_effect_id')->references('id')->on('special_effects')->nullOnDelete();
        });
        Schema::table('quote_addons', function (Blueprint $table) {
            $table->dropForeign(['addon_id']);
            $table->unsignedBigInteger('addon_id')->nullable()->change();
            $table->foreign('addon_id')->references('id')->on('addons')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Keep these references nullable because a configuration row may have
        // been purged after its name was snapshotted on a quotation.
        Schema::table('quote_effects', function (Blueprint $table) {
            $table->dropForeign(['special_effect_id']);
            $table->foreign('special_effect_id')->references('id')->on('special_effects')->restrictOnDelete();
            $table->dropColumn(['effect_code', 'effect_name']);
        });
        Schema::table('quote_addons', function (Blueprint $table) {
            $table->dropForeign(['addon_id']);
            $table->foreign('addon_id')->references('id')->on('addons')->restrictOnDelete();
            $table->dropColumn(['addon_code', 'addon_name']);
        });
    }
};
