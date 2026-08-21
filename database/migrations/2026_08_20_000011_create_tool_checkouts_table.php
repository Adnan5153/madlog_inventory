<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout / check-in history for tools. Each row represents a single
 * movement event of a tool to/from a user. The "open" checkout (the
 * one whose `returned_at IS NULL`) is the tool's current holder.
 *
 * App-level invariant: only one open checkout per `tool_id` at a time.
 * Enforced by ToolCheckoutService::checkout().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('checked_out_at');
            $table->timestamp('expected_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('purpose', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('condition_at_return', 32)->nullable();
            $table->string('status', 24)->default('open');

            $table->timestamps();

            $table->index(['tool_id', 'returned_at']);
            $table->index(['workshop_id', 'user_id', 'returned_at']);
            $table->index(['workshop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_checkouts');
    }
};
