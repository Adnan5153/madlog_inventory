<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use App\Services\Access\RolePermissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers DB-backed permissions as Laravel Gates.
 *
 * - `Gate::before()` short-circuits with true for admins (users with
 *   role=admin, i.e. User::isAdmin()) — they implicitly pass every
 *   check. This mirrors Laravel's `super-admin` convention.
 * - For every Permission row in the database, `Gate::define()` is
 *   registered with the permission name as the ability. The closure
 *   delegates to `RolePermissionService::userHas`, which uses
 *   `User::hasPermission()` (cached per request on the user model).
 *
 * Registration: bootstrap/providers.php.
 */
class PermissionRegistrar extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerSuperAdminBypass();
        $this->registerPermissionGates();
    }

    /**
     * Global admins satisfy every gate before the closure runs.
     */
    protected function registerSuperAdminBypass(): void
    {
        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }

            return null; // fall through to the ability's own check
        });
    }

    /**
     * One Gate per Permission row. Done at boot() time — DB is small
     * (≈70 rows) and bootstrap cost is negligible.
     *
     * If the `permissions` table doesn't yet exist (e.g. during the
     * first migration), we silently skip — the Gate lookup falls back
     * to the authorization closures for that request, and the next
     * request after migrations will re-run with rows present.
     */
    protected function registerPermissionGates(): void
    {
        try {
            $rows = Permission::query()->select(['id', 'name'])->get();
        } catch (\Throwable) {
            return;
        }

        $service = $this->app->make(RolePermissionService::class);

        $rows->each(function (Permission $permission) use ($service) {
            Gate::define($permission->name, function (User $user) use ($service, $permission) {
                return $service->userHas($user, $permission->name);
            });
        });
    }
}
