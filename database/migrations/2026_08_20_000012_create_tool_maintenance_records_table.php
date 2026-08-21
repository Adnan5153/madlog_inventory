<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only maintenance history for tools. Each row records a single
 * maintenance event (preventive, repair, cleaning, etc.) with optional
 * `next_due_at` so the dashboard can surface upcoming/overdue work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();

            $table->string('type', 32);
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendor', 160)->nullable();
            $table->decimal('cost', 12, 2)->nullable();

            $table->timestamp('performed_at');
            $table->timestamp('next_due_at')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tool_id', 'performed_at']);
            $table->index(['workshop_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_maintenance_records');
    }
};
