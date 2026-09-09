<?php

use App\Support\ProductionPipeline;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Labour costed by the work the job actually causes.
     *
     * One flat figure per piece was wrong for the same reason a per-minute
     * figure was: a full sublimation job runs laser cutting and a roller press,
     * a DTF job runs neither, and both were being charged the same. Each stage
     * of the floor now carries its own rate, and the print type on a line says
     * which stages that job passes through.
     *
     * Stages that happen once however many pieces are ordered - layout, the
     * mockup, the client sample, releasing the order - are charged once on the
     * quotation as setup, which is what makes a run of ten honestly dearer per
     * piece than a run of a hundred.
     */
    public function up(): void
    {
        Schema::create('production_stages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            // per_piece or per_job.
            $table->string('basis');
            $table->unsignedInteger('sequence');
            $table->decimal('rate', 12, 2)->default(0);
            $table->timestamps();
        });

        // The shop's existing flat labour becomes the sewing rate: it is the
        // stage that figure was really standing in for, and it keeps quotations
        // pricing at roughly what they did before the stages existed.
        $flat = (float) (DB::table('settings')->where('key', 'default_labour_cost')->value('value') ?? 0);

        foreach (ProductionPipeline::stages() as $key => $stage) {
            DB::table('production_stages')->insert([
                'key' => $key,
                'label' => $stage['label'],
                'basis' => $stage['basis'],
                'sequence' => $stage['sequence'],
                'rate' => $key === 'sewing' ? $flat : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('settings')->where('key', 'default_labour_cost')->delete();

        Schema::table('quotation_items', function (Blueprint $table) {
            // Which route through the floor this line takes.
            $table->string('print_type')->nullable()->after('product_sku');
        });

        Schema::table('quotations', function (Blueprint $table) {
            // The once-per-job stages, charged once for the whole quotation.
            $table->decimal('setup_cost', 14, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('setup_cost');
        });

        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('print_type');
        });

        Schema::dropIfExists('production_stages');
    }
};
