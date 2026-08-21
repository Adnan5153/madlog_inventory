<?php

namespace App\Policies;

use App\Models\Lubricant;
use App\Models\User;

/**
 * Lubricant catalog. All authenticated staff can read; only admins with
 * the `lubricants.{action}` permission may write.
 */
class LubricantPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, Lubricant $lubricant): bool
    {
        return $this->userCanAccessWorkshop($user, $lubricant->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Lubricant $lubricant): bool
    {
        return $this->userCanAccessWorkshop($user, $lubricant->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, Lubricant $lubricant): bool
    {
        return $this->userCanAccessWorkshop($user, $lubricant->workshop_id)
            && $user->isAdmin();
    }
}
