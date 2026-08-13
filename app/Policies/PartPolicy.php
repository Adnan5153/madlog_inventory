<?php

namespace App\Policies;

use App\Models\Part;
use App\Models\User;

/**
 * Part catalog. All authenticated staff/admin can read. Only admins can
 * create, update, or delete parts; staff can only read.
 */
class PartPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, Part $part): bool
    {
        return $this->userCanAccessWorkshop($user, $part->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Part $part): bool
    {
        return $this->userCanAccessWorkshop($user, $part->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, Part $part): bool
    {
        return $this->userCanAccessWorkshop($user, $part->workshop_id)
            && $user->isAdmin();
    }
}
