<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // "Piece", "Liter", "Kilogram"
            $table->string('short_code', 8);        // "pc", "L", "kg"
            $table->string('description')->nullable();
            $table->unsignedTinyInteger('decimal_precision')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('short_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
