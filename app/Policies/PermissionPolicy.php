<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;

/**
 * Read-only catalogue. Anyone with `roles.manage` may browse the
 * catalogue; mutations aren't possible from the UI — permissions are
 * curated via Roles.
 */
class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermission('roles.manage');
    }
}
