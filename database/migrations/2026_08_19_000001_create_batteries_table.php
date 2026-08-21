<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batteries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();

            // Identification
            $table->string('battery_code', 64);
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->string('barcode', 64)->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('manufacturer_part_number', 64)->nullable();
            $table->text('description')->nullable();

            // Classification — see App\Enums\BatteryChemistry, BatteryApplication, BatteryCondition, BatteryStatus
            $table->string('battery_type', 32)->default('lead_acid');
            $table->string('application_type', 32)->nullable();
            $table->string('condition', 32)->default('new');
            $table->string('status', 32)->default('active');

            // Tech specs
            $table->decimal('voltage', 6, 2)->nullable();
            $table->decimal('capacity_ah', 8, 2)->nullable();
            $table->unsignedInteger('cold_cranking_amps')->nullable();
            $table->unsignedInteger('reserve_capacity')->nullable();
            $table->string('terminal_type', 32)->nullable();
            $table->decimal('length_mm', 8, 2)->nullable();
            $table->decimal('width_mm', 8, 2)->nullable();
            $table->decimal('height_mm', 8, 2)->nullable();
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->string('polarity', 16)->nullable();

            // Commercial
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();

            // Reorder policy
            $table->unsignedInteger('reorder_threshold')->default(0);
            $table->unsignedInteger('reorder_quantity')->default(0);

            // Warranty
            $table->unsignedSmallInteger('warranty_period_months')->nullable();
            $table->date('warranty_expiry')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workshop_id', 'battery_code'], 'batteries_workshop_code_unique');
            $table->unique(['workshop_id', 'sku'], 'batteries_workshop_sku_unique');
            $table->unique(['workshop_id', 'barcode'], 'batteries_workshop_barcode_unique');
            $table->index(['workshop_id', 'name']);
            $table->index(['workshop_id', 'battery_type']);
            $table->index(['workshop_id', 'brand']);
            $table->index(['workshop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batteries');
    }
};
