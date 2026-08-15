<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft-delete support to the users and roles tables.
 *
 * Without this, deleting a user performs a hard DB DELETE that fires the
 * AuditObserver `deleted` event AFTER the row is gone — and the audit
 * insert fails its FK on `user_id`. Soft-deleting users also lets us
 * retain their purchase order / stock movement history even after the
 * user is "deleted".
 *
 * Roles also gain soft-deletes so the role form-request unique rule
 * (`name`, `slug`) can reference `deleted_at IS NULL` and we can
 * restore an archived role later.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'roles'] as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['users', 'roles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
