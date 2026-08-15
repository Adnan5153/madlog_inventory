<?php

namespace App\Policies;

use App\Models\BinLocation;
use App\Models\User;

class BinLocationPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, BinLocation $bin): bool
    {
        return $this->userCanAccessWorkshop($user, $bin->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->workshop_id !== null;
    }

    public function update(User $user, BinLocation $bin): bool
    {
        return $this->userCanAccessWorkshop($user, $bin->workshop_id) && $user->isAdmin();
    }

    public function delete(User $user, BinLocation $bin): bool
    {
        return $this->userCanAccessWorkshop($user, $bin->workshop_id) && $user->isAdmin();
    }
}
