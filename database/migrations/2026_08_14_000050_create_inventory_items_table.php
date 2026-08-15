<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // inventory_items: per-(part, bin) stock bucket. The actual on-hand
        // quantity is the sum of stock_movements rows; this table stores the
        // cached running total for fast lookups. The InventoryService is
        // responsible for keeping this in sync inside a transaction.
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('batch_number')->nullable();
            $table->decimal('quantity', 12, 2)->default(0); // current on-hand
            $table->decimal('reserved_quantity', 12, 2)->default(0); // allocated to job cards
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->timestamps();

            // One row per (part, bin, batch) combination — supports same part in multiple bins.
            $table->unique(['part_id', 'bin_id', 'batch_number'], 'inventory_part_bin_batch_unique');
            $table->index(['workshop_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
