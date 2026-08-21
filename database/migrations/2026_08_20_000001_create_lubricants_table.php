<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lubricants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();

            // Identification
            $table->string('lubricant_code', 64);
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->string('barcode', 64)->nullable();
            $table->string('brand', 120)->nullable();
            $table->string('manufacturer', 120)->nullable();
            $table->string('manufacturer_part_number', 64)->nullable();
            $table->text('description')->nullable();

            // Classification — see App\Enums\LubricantType, LubricantViscosity, LubricantApplication, LubricantStatus
            $table->string('lubricant_type', 32)->default('mineral');
            $table->string('viscosity_grade', 32)->nullable();
            $table->string('application_type', 32)->nullable();
            $table->string('status', 32)->default('active');

            // Technical specifications
            $table->string('oem_specification', 128)->nullable();
            $table->string('acea_specification', 64)->nullable();
            $table->string('api_specification', 64)->nullable();
            $table->string('iso_grade', 32)->nullable();
            $table->string('nlgi_grade', 32)->nullable();

            // Packaging — see App\Enums\LubricantPackageType
            $table->string('package_type', 32)->default('bottle');
            $table->decimal('package_size', 10, 2)->default(0);
            $table->string('package_unit', 16)->default('L');

            // Commercial + storage
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->default(0);
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();

            // Reorder policy
            $table->unsignedInteger('reorder_threshold')->default(0);
            $table->unsignedInteger('reorder_quantity')->default(0);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workshop_id', 'lubricant_code'], 'lubricants_workshop_code_unique');
            $table->unique(['workshop_id', 'sku'], 'lubricants_workshop_sku_unique');
            $table->unique(['workshop_id', 'barcode'], 'lubricants_workshop_barcode_unique');
            $table->index(['workshop_id', 'name']);
            $table->index(['workshop_id', 'lubricant_type']);
            $table->index(['workshop_id', 'brand']);
            $table->index(['workshop_id', 'application_type']);
            $table->index(['workshop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lubricants');
    }
};
