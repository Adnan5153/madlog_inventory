<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->foreignId('bin_location_id')
                ->nullable()
                ->after('description')
                ->constrained('bin_locations')
                ->nullOnDelete();
            $table->string('location')
                ->nullable()
                ->after('bin_location_id');
        });
    }

    public function down(): void
    {
        Schema::table('parts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bin_location_id');
            $table->dropColumn('location');
        });
    }
};
