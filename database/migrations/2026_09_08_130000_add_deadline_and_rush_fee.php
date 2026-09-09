<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A rush fee, driven by when the customer needs the job.
     *
     * Work content and turnaround pull in opposite directions: a fiddly print
     * costs more because it takes longer, while a job wanted sooner costs more
     * because it displaces everything else on the press. The first is a cost
     * and lives in the labour minutes; this is a price, and lives here.
     *
     * The uplift is charged on the quotation rather than folded into each unit
     * price, so the customer can see what the deadline cost them and the unit
     * prices still say what a piece is worth.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // When the customer needs the job, as against valid_until, which is
            // how long the quotation itself stands.
            $table->date('deadline')->nullable()->after('valid_until');
            $table->decimal('subtotal', 14, 2)->default(0)->after('total');
            $table->decimal('rush_percentage', 7, 4)->default(0)->after('subtotal');
            $table->decimal('rush_amount', 14, 2)->default(0)->after('rush_percentage');
        });

        // Quotations written before rush fees existed were their own subtotal.
        DB::table('quotations')->update(['subtotal' => DB::raw('total')]);

        Schema::create('rush_tiers', function (Blueprint $table) {
            $table->id();
            // Wanted within this many days of the quotation being written.
            $table->unsignedInteger('within_days');
            $table->decimal('surcharge_percentage', 7, 4);
            $table->timestamps();
            $table->unique('within_days');
        });

        // The rule as asked for: a week or less is a rush. The percentage is a
        // starting point to be set on the Settings screen, not a house rate.
        DB::table('rush_tiers')->insert([
            'within_days' => 7,
            'surcharge_percentage' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('rush_tiers');
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['deadline', 'subtotal', 'rush_percentage', 'rush_amount']);
        });
    }
};
