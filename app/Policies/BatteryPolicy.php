<?php

namespace App\Policies;

use App\Models\Battery;
use App\Models\User;

/**
 * Battery catalog. All authenticated staff can read; only admins with
 * the `batteries.{action}` permission may write.
 */
class BatteryPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, Battery $battery): bool
    {
        return $this->userCanAccessWorkshop($user, $battery->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Battery $battery): bool
    {
        return $this->userCanAccessWorkshop($user, $battery->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, Battery $battery): bool
    {
        return $this->userCanAccessWorkshop($user, $battery->workshop_id)
            && $user->isAdmin();
    }
}
