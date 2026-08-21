<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-(lubricant, bin, batch) bucket that holds the current on-hand
        // quantity. Mirror of inventory_items but scoped to lubricants.
        Schema::create('lubricant_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('lubricant_id')->constrained('lubricants')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            $table->string('batch_number')->nullable();
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('reserved_quantity', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['lubricant_id', 'bin_id', 'batch_number'], 'lubricant_inventory_lubricant_bin_batch_unique');
            $table->index(['workshop_id', 'lubricant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lubricant_inventory_items');
    }
};
