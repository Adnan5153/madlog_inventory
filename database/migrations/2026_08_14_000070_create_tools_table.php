<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('name');
            $table->string('asset_tag')->nullable();
            $table->string('serial_number')->nullable();
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('value', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['workshop_id', 'asset_tag']);
        });

        Schema::create('tool_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->foreignId('tool_id')->constrained('tools')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // staff member
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('checked_out_at');
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('expected_return_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workshop_id', 'user_id']);
            $table->index(['tool_id', 'returned_at']);
            // Prevent double-checkout: an open checkout exists for a tool only when returned_at is null.
            // Enforced in application code; see ToolCheckoutService.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_checkouts');
        Schema::dropIfExists('tools');
    }
};