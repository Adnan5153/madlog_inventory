<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

/**
 * Units are global master data (no workshop_id). Any admin can manage
 * them; staff users are blocked at middleware level. Per Phase 6, this
 * policy will check `settings.view` / `settings.manage` permissions.
 */
class UnitPolicy
{
    public function viewAny(?User $user): bool
    {
        return $user?->isAdmin() ?? false;
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Unit $unit): bool
    {
        // Don't allow deleting a unit that's still in use.
        return $user->isAdmin()
            && $unit->parts()->doesntExist()
            && $unit->binLocations()->doesntExist();
    }
}
