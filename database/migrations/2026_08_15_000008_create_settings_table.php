<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->json('value')->nullable();
            $table->foreignId('workshop_id')->nullable()->constrained('workshops')->cascadeOnDelete();
            $table->string('group')->default('general');   // general | inventory | procurement | numbering | notifications
            $table->string('type')->default('string');     // string | int | bool | json
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['key', 'workshop_id']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};