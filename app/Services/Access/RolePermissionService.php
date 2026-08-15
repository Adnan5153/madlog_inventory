<?php

namespace App\Services\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves and manages role/permission grants.
 *
 * Reads are delegated to User::loadPermissions() (cached per request),
 * writes go through `sync*` methods that wrap in transactions so the
 * pivot rows stay consistent.
 */
class RolePermissionService
{
    /**
     * Check whether $user has $permissionName.
     *
     * - Admins (role=admin) implicitly satisfy every check.
     * - Otherwise consults the user's effective permission set
     *   (union of role permissions + direct grants, excluding expired
     *   direct grants).
     */
    public function userHas(User $user, string $permissionName): bool
    {
        return $user->hasPermission($permissionName);
    }

    /**
     * Sync the permission set on $role. Detaches everything not in
     * $permissionIds, attaches everything that is.
     *
     * @param  array<int>  $permissionIds
     */
    public function syncRolePermissions(Role $role, array $permissionIds): void
    {
        DB::transaction(function () use ($role, $permissionIds) {
            $role->syncPermissions($permissionIds);
        });
    }

    /**
     * All permissions grouped for UI rendering.
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    public function permissionsGrouped(): Collection
    {
        return Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');
    }

    /**
     * Convenience for tests / dashboards: count of distinct permissions
     * a user currently has, ignoring expiry.
     */
    public function userPermissionCount(User $user): int
    {
        return $user->loadPermissions()->count();
    }

    /**
     * Grant a permission to a user directly with optional expiry.
     */
    public function grantToUser(User $user, Permission $permission, ?Carbon $expiresAt = null): void
    {
        $user->givePermissionTo($permission, $expiresAt);
    }

    /**
     * Revoke a direct permission grant.
     */
    public function revokeFromUser(User $user, Permission $permission): void
    {
        $user->revokePermission($permission);
    }

    /**
     * Find or create a permission by name. Used by the seeder; safer
     * than `firstOrCreate` because group + description can be filled
     * on creation.
     */
    public function ensurePermission(string $name, string $group, ?string $description = null): Permission
    {
        $perm = Permission::query()->where('name', $name)->first();
        if ($perm) {
            return $perm;
        }

        return Permission::create([
            'name' => $name,
            'group' => $group,
            'description' => $description,
        ]);
    }

    /**
     * Find or create a role.
     */
    public function ensureRole(string $name, string $slug, ?string $description = null, bool $isSystem = false): Role
    {
        $role = Role::query()->where('slug', $slug)->first();
        if ($role) {
            return $role;
        }

        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'is_system' => $isSystem,
        ]);
    }
}
