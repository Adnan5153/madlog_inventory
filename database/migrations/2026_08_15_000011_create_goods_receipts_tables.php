<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('grn_number', 32);                       // human-friendly
            $table->string('supplier_invoice_number', 64)->nullable();
            $table->string('status', 24)->default('received')->index(); // received, partial, disputed
            $table->dateTime('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['workshop_id', 'grn_number']);
            $table->index(['workshop_id', 'purchase_order_id']);
            $table->index(['workshop_id', 'received_at']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('quantity_received', 12, 2);
            $table->decimal('damaged_quantity', 12, 2)->default(0);
            $table->string('batch_number', 64)->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('unit_cost', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('goods_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
