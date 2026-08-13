<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();

            $table->string('name');
            $table->string('asset_number')->nullable();
            $table->string('equipment_type')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('purchase_date')->nullable();
            $table->date('warranty_expires_at')->nullable();

            $table->string('status')->default('active'); // active | maintenance | retired | disposed
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['workshop_id', 'asset_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};