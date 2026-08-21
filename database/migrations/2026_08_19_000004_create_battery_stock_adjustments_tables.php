<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('battery_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('reference', 32);
            $table->string('status', 24)->default('pending')->index();
            $table->string('reason', 64);
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workshop_id', 'reference']);
            $table->index(['workshop_id', 'status']);
        });

        Schema::create('battery_stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('battery_stock_adjustment_id');
            $table->foreignId('battery_id')->constrained('batteries')->restrictOnDelete();
            $table->foreignId('battery_inventory_item_id')->nullable()->constrained('battery_inventory_items')->nullOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->decimal('counted_quantity', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('battery_stock_adjustment_id', 'bsai_adjustment_fk')
                ->references('id')->on('battery_stock_adjustments')
                ->cascadeOnDelete();
            $table->index('battery_stock_adjustment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_stock_adjustment_items');
        Schema::dropIfExists('battery_stock_adjustments');
    }
};
