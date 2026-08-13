<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workshop;

/**
 * Workshop is the tenant entity. Creating / updating is restricted to
 * global admins (admin role + workshop_id = null). Workshop-scoped admins
 * can read their own workshop record.
 */
class WorkshopPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, Workshop $workshop): bool
    {
        return $this->userCanAccessWorkshop($user, $workshop->id);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdmin();
    }

    public function update(User $user, Workshop $workshop): bool
    {
        return $this->userCanAccessWorkshop($user, $workshop->id) && $user->isAdmin();
    }

    public function delete(User $user, Workshop $workshop): bool
    {
        return $user->isGlobalAdmin();
    }
}
