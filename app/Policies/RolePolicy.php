<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Role management is gated by the `roles.manage` permission. Global
 * admins satisfy this via the super-admin fast-path.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }
        return $user->hasPermission('roles.manage');
    }
}