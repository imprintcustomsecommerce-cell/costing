<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Labour is a job figure with a shop-wide default, not a catalogue field.
     *
     * What the work on a piece costs is decided when the job is quoted, so the
     * quotation line is where it is entered. The only thing worth holding in
     * advance is the shop's usual figure, and that is one number for the shop
     * rather than one per product - so it lives in settings beside the margin
     * and the rush fees.
     */
    public function up(): void
    {
        // The highest figure any product carried is the closest thing to a
        // shop default that exists, so nothing is silently lost.
        $existing = (float) (DB::table('products')->max('labour_cost') ?? 0);

        DB::table('settings')->updateOrInsert(
            ['key' => 'default_labour_cost'],
            [
                'value' => (string) $existing,
                'type' => 'decimal',
                'group' => 'company',
                'label' => 'Default Labour Cost',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('labour_cost');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('labour_cost', 12, 2)->nullable()->after('description');
        });

        DB::table('settings')->where('key', 'default_labour_cost')->delete();
    }
};
