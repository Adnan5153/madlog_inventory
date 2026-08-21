<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only ledger for every change to battery stock. Mirror of
        // stock_movements but scoped to batteries. Corrections require
        // a reversing movement of the same type; boot hooks on the
        // BatteryStockMovement model enforce this.
        Schema::create('battery_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('battery_id')->constrained('batteries')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('battery_inventory_item_id')->nullable()->constrained('battery_inventory_items')->nullOnDelete();

            $table->string('type', 32);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['workshop_id', 'occurred_at']);
            $table->index(['workshop_id', 'type']);
            $table->index(['battery_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_stock_movements');
    }
};
