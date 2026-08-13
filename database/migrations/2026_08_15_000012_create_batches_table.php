<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->cascadeOnDelete();
            $table->string('batch_number', 64);
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('initial_quantity', 12, 2)->default(0);
            $table->decimal('current_quantity', 12, 2)->default(0);
            $table->string('status', 24)->default('active')->index(); // active, depleted, expired, recalled
            $table->timestamps();

            $table->unique(['workshop_id', 'part_id', 'batch_number']);
            $table->index(['workshop_id', 'expires_at']);
            $table->index(['workshop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
