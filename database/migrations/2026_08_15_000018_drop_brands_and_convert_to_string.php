<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the brands CRUD module:
     *   1. Add a free-text `brand` column on `parts`.
     *   2. Backfill it from the existing `brands` join so no data is lost.
     *   3. Drop the `brand_id` foreign key + column.
     *   4. Drop the `brands` table.
     */
    public function up(): void
    {
        // 1. Add the new free-text column.
        Schema::table('parts', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('oem_part_number');
        });

        // 2. Backfill from the existing brands join (only when the brands
        //    table is still present, e.g. on a fresh migrate this is skipped).
        if (Schema::hasTable('brands')) {
            DB::statement(
                'UPDATE parts p '
                .'JOIN brands b ON b.id = p.brand_id '
                .'SET p.brand = b.name '
                .'WHERE p.brand_id IS NOT NULL'
            );
        }

        // 3. Drop the foreign key + column on `parts`.
        Schema::table('parts', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });

        // 4. Drop the `brands` table.
        Schema::dropIfExists('brands');
    }

    public function down(): void
    {
        // Re-create the brands table shell.
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained('workshops')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['workshop_id', 'slug']);
        });

        // Re-add the foreign key on parts.
        Schema::table('parts', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('oem_part_number')->constrained('brands')->nullOnDelete();
        });

        // Best-effort reverse copy: we can't fully reconstruct the brand
        // rows, so this is intentionally lossy.
        if (Schema::hasColumn('parts', 'brand')) {
            DB::statement(
                'INSERT INTO brands (workshop_id, name, slug, created_at, updated_at) '
                .'SELECT workshop_id, brand, '
                ."LOWER(REPLACE(REPLACE(REPLACE(brand, ' ', '-'), '/', '-'), '--', '-')), "
                .'NOW(), NOW() '
                .'FROM parts '
                ."WHERE brand IS NOT NULL AND brand <> '' "
                .'GROUP BY workshop_id, brand'
            );
        }

        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }
};
