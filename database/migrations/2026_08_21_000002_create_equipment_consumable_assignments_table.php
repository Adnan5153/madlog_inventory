<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment consumable assignments — append-only lifecycle event log.
 *
 * Each row records a single lifecycle verb (assigned / installed / consumed
 * / replaced / removed) for one equipment_consumable. Consumed and
 * remove-with-return rows also reference the matching stock_movement ledger
 * row polymorphically (part / battery / lubricant).
 *
 * No soft deletes — corrections are recorded as new rows with status
 * 'cancelled'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_consumable_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('equipment_consumable_id')
                ->constrained('equipment_consumables')
                ->cascadeOnDelete();

            $table->string('type', 32);     // assigned|installed|consumed|replaced|removed
            $table->string('status', 32);   // assigned|installed|consumed|removed|cancelled

            $table->decimal('quantity', 12, 3);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('total_cost', 14, 4)->nullable();

            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at');

            // Replacement chain — this assignment replaces an earlier one.
            $table->foreignId('previous_assignment_id')
                ->nullable()
                ->constrained('equipment_consumable_assignments')
                ->nullOnDelete();

            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();

            // Polymorphic reference to the stock_movement ledger row.
            $table->string('stock_movement_type', 32)->nullable(); // part|battery|lubricant
            $table->unsignedBigInteger('stock_movement_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['equipment_consumable_id', 'performed_at'], 'eca_ec_at_idx');
            $table->index(['workshop_id', 'type'], 'eca_ws_type_idx');
            $table->index(['workshop_id', 'performed_at'], 'eca_ws_at_idx');
            $table->index(['stock_movement_type', 'stock_movement_id'], 'eca_sm_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_consumable_assignments');
    }
};
