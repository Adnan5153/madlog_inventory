<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_cards', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 32);
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('mechanic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->string('vehicle_make')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_plate')->nullable();
            $table->string('vehicle_vin')->nullable();

            $table->string('status', 24)->default('open')->index(); // open, in_progress, completed, cancelled
            $table->text('description')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['workshop_id', 'job_number']);
            $table->index(['workshop_id', 'status']);
            $table->index(['workshop_id', 'mechanic_id']);
        });

        // job_card_parts: records every part attached to a job card,
        // whether it was consumed, returned, or is still reserved.
        Schema::create('job_card_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('job_card_id')->constrained('job_cards')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('parts')->restrictOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('quantity', 12, 2);                    // how many were reserved
            $table->decimal('quantity_consumed', 12, 2)->default(0);
            $table->decimal('quantity_returned', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('status', 24)->default('reserved')->index(); // reserved, consumed, returned, partial
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['job_card_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_card_parts');
        Schema::dropIfExists('job_cards');
    }
};