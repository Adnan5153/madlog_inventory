<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bin_locations', function (Blueprint $table) {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('description')
                ->constrained('units')
                ->nullOnDelete();
            $table->decimal('capacity', 12, 2)->nullable()->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('bin_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('capacity');
        });
    }
};
