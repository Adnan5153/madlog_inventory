<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $this->userCanAccessWorkshop($user, $equipment->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $this->userCanAccessWorkshop($user, $equipment->workshop_id);
    }

    public function delete(User $user, Equipment $equipment): bool
    {
        return $this->userCanAccessWorkshop($user, $equipment->workshop_id);
    }
}