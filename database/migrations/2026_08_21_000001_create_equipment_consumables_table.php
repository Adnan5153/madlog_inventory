<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment ↔ consumable junction.
 *
 * One row per (equipment, resource) pairing that's currently tracked.
 * Lifecycle events are recorded as rows on `equipment_consumable_assignments`;
 * this table is the durable "this equipment has this consumable" record.
 * Workshop-scoped, soft-deletable (deletions are recoverable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_consumables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();

            // Polymorphic resource — Part | Battery | Lubricant
            $table->string('resource_type', 64);
            $table->unsignedBigInteger('resource_id');

            $table->dateTime('assigned_at');
            $table->date('expected_replacement_at')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['workshop_id', 'equipment_id'], 'ec_ws_equip_idx');
            $table->index(['resource_type', 'resource_id'], 'ec_resource_idx');
            $table->index(['workshop_id', 'expected_replacement_at'], 'ec_ws_repl_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_consumables');
    }
};
