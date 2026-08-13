<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Role is required for staff/admin/public authorization decisions.
            // Nullable for backwards-compatibility with the default Laravel user.
            $table->string('role', 16)->nullable()->after('password')->index();

            // Nullable workshop_id: a global admin (workshop_id = null) can manage
            // all workshops; a workshop-scoped admin/staff is bound to one workshop.
            // FK is added in a separate migration after the workshops table exists.
            $table->foreignId('workshop_id')->nullable()->after('role')->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['workshop_id']);
            $table->dropColumn(['role', 'workshop_id']);
        });
    }
};