<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('part_categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();

            $table->string('sku')->nullable();              // workshop-local SKU
            $table->string('oem_part_number')->nullable(); // manufacturer number
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->text('description')->nullable();

            // Reorder policy
            $table->unsignedInteger('reorder_threshold')->default(0);
            $table->unsignedInteger('reorder_quantity')->default(0);

            // Pricing
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Workshop-scoped uniqueness for both SKU and barcode.
            $table->unique(['workshop_id', 'sku'], 'parts_workshop_sku_unique');
            $table->unique(['workshop_id', 'barcode'], 'parts_workshop_barcode_unique');

            // Search indexes.
            $table->index(['workshop_id', 'name']);
            $table->index(['workshop_id', 'oem_part_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
