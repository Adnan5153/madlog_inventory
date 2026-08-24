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
        //    Use the query builder so the SQL is portable across drivers
        //    (MySQL, PostgreSQL, SQLite). Raw MySQL-style `UPDATE ... JOIN`
        //    syntax would fail on SQLite which the CI uses.
        if (Schema::hasTable('brands')) {
            $brandRows = DB::table('brands')->select('id', 'name')->get();
            foreach ($brandRows as $brand) {
                DB::table('parts')
                    ->where('brand_id', $brand->id)
                    ->whereNotNull('brand_id')
                    ->update(['brand' => $brand->name]);
            }
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
        // rows, so this is intentionally lossy. Uses portable query builder
        // (no MySQL-only NOW() / multi-arg REPLACE chain) so down() works on
        // SQLite as well as MySQL.
        if (Schema::hasColumn('parts', 'brand')) {
            $brandsByWorkshop = DB::table('parts')
                ->select('workshop_id', 'brand')
                ->whereNotNull('brand')
                ->where('brand', '<>', '')
                ->groupBy('workshop_id', 'brand')
                ->get();
            $now = now();
            foreach ($brandsByWorkshop as $row) {
                $slug = strtolower(str_replace([' ', '/', '--'], '-', $row->brand));
                DB::table('brands')->insert([
                    'workshop_id' => $row->workshop_id,
                    'name' => $row->brand,
                    'slug' => $slug,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('parts', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }
};
