<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-(battery, bin, batch) bucket that holds the current on-hand
        // quantity. Mirror of inventory_items but scoped to batteries.
        Schema::create('battery_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('battery_id')->constrained('batteries')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('batch_number')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['battery_id', 'bin_id', 'batch_number'], 'battery_inventory_battery_bin_batch_unique');
            $table->index(['workshop_id', 'battery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_inventory_items');
    }
};
