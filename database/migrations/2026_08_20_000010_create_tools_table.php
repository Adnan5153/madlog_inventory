<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical operational tools (torque wrenches, scanners, jacks, etc.).
 * Each tool is a single serialized asset, NOT bucketed stock — there
 * is no `tool_inventory_items` or quantity tracking. Lifecycle columns
 * (status, condition, holder) live directly on the row.
 *
 * See App\Models\Tool, App\Enums\ToolStatus, App\Enums\ToolCondition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();

            // Identification
            $table->string('tool_code', 64);
            $table->string('name', 160);
            $table->foreignId('category_id')->nullable()->constrained('tool_categories')->nullOnDelete();
            $table->string('brand', 120)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('serial_number', 64)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->string('qr_code', 64)->nullable();
            $table->text('description')->nullable();

            // Lifecycle
            $table->string('condition', 32)->default('good');
            $table->string('status', 32)->default('available');
            $table->foreignId('current_holder_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);

            // Location
            $table->foreignId('bin_id')->nullable()->constrained('bin_locations')->nullOnDelete();

            // Acquisition (cost only — no selling price)
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->date('warranty_expiry')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workshop_id', 'tool_code'], 'tools_workshop_code_unique');
            $table->index(['workshop_id', 'status']);
            $table->index(['workshop_id', 'condition']);
            $table->index(['workshop_id', 'category_id']);
            $table->index(['workshop_id', 'is_active']);
            $table->index(['workshop_id', 'current_holder_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tools');
    }
};
