<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bin_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('code');          // e.g. "A-12"
            $table->string('zone')->nullable(); // e.g. "Brakes", "Engine"
            $table->string('aisle')->nullable();
            $table->string('shelf')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['workshop_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_locations');
    }
};