<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global application settings (company info, currency, tax, defaults).
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string|decimal|integer|boolean|json
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        // A named snapshot point of the whole pricing configuration.
        Schema::create('pricing_versions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();         // e.g. 2026-08-30-V4
            $table->string('label')->nullable();
            $table->text('notes')->nullable();
            $table->date('effective_from');
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'effective_from']);
        });

        // How a calculated selling price is rounded. Admin configurable.
        Schema::create('rounding_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope')->default('global');      // global|print_method|customer_type
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('mode')->default('nearest');      // nearest|up|down|none
            $table->decimal('increment', 12, 2)->default(1); // 1, 5, 10, 50, 100
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'scope_id', 'is_active']);
        });

        // Sensitive-action audit trail. Append-only by policy.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');                        // created|updated|deleted|approved|...
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('rounding_rules');
        Schema::dropIfExists('pricing_versions');
        Schema::dropIfExists('settings');
    }
};
