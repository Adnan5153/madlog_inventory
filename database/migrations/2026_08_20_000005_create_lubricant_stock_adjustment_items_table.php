<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lubricant_stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lubricant_stock_adjustment_id');
            $table->foreignId('lubricant_id')->constrained('lubricants')->restrictOnDelete();
            $table->foreignId('lubricant_inventory_item_id')->nullable()
                ->constrained('lubricant_inventory_items', indexName: 'lsai_inventory_fk')
                ->nullOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('counted_quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('lubricant_stock_adjustment_id', 'lsai_adjustment_fk')
                ->references('id')->on('lubricant_stock_adjustments')
                ->cascadeOnDelete();
            $table->index('lubricant_stock_adjustment_id', 'lsai_adjustment_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lubricant_stock_adjustment_items');
    }
};
