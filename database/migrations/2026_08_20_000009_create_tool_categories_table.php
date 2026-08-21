<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories used to organize tools into meaningful operational groups
 * (Hand Tools, Power Tools, Diagnostic Tools, Lifting Equipment, etc.).
 * Workshop-scoped; deletion is blocked when categories are in use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workshop_id', 'name'], 'tool_categories_workshop_name_unique');
            $table->index(['workshop_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_categories');
    }
};
