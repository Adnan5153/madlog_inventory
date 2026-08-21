<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the legacy `tools` and `tool_checkouts` tables created by
 * `2026_08_14_000070_create_tools_table.php`. The Tools module is being
 * rebuilt with a fuller schema (tool_code natural key, categories,
 * condition, status, supplier, purchase info, warranty, maintenance
 * records) and a dedicated checkout workflow.
 *
 * The `down()` re-creates the original tables verbatim so this migration
 * remains reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tool_checkouts');
        Schema::dropIfExists('tools');
    }

    public function down(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('name');
            $table->string('asset_tag')->nullable();
            $table->string('serial_number')->nullable();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('value', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['workshop_id', 'asset_tag']);
        });

        Schema::create('tool_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_out_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('expected_return_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workshop_id', 'user_id']);
            $table->index(['tool_id', 'returned_at']);
        });
    }
};
