<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lubricant_stock_adjustments', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('lubricant_stock_adjustments');
    }
};
