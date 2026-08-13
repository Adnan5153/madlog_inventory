<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // stock_movements: append-only ledger of every change to inventory.
        // Never UPDATE or DELETE these rows; corrections require a reversing movement.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();

            // Movement semantics — see App\Enums\StockMovementType for the full list.
            $table->string('type', 32);
            $table->decimal('quantity', 12, 2); // signed: +receipt, -issue
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable();  // polymorphic to PO, JobCard, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('reason')->nullable();           // required for manual_adjustment
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['workshop_id', 'occurred_at']);
            $table->index(['workshop_id', 'type']);
            $table->index(['part_id', 'occurred_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};