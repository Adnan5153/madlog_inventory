<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 prerequisite (see plan §Q risk #2): without soft deletes on these
 * tables, deleting a workshop cascades and wipes BinLocations, InventoryItems,
 * and everything else. Soft deletes convert the destructive cascade into
 * archival.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['workshops', 'parts', 'bin_locations', 'suppliers', 'purchase_orders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['workshops', 'parts', 'bin_locations', 'suppliers', 'purchase_orders'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
