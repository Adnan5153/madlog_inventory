<?php

namespace App\Policies;

use App\Models\EquipmentConsumable;
use App\Models\User;

/**
 * Equipment consumables: any workshop-scoped user can view. Only admins
 * can mutate (assign / consume / replace / remove).
 */
class EquipmentConsumablePolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, EquipmentConsumable $consumable): bool
    {
        return $this->userCanAccessWorkshop($user, $consumable->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EquipmentConsumable $consumable): bool
    {
        return $this->userCanAccessWorkshop($user, $consumable->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, EquipmentConsumable $consumable): bool
    {
        return $this->userCanAccessWorkshop($user, $consumable->workshop_id)
            && $user->isAdmin();
    }

    public function consume(User $user, EquipmentConsumable $consumable): bool
    {
        return $this->userCanAccessWorkshop($user, $consumable->workshop_id)
            && $user->isAdmin();
    }

    public function replace(User $user, EquipmentConsumable $consumable): bool
    {
        return $this->userCanAccessWorkshop($user, $consumable->workshop_id)
            && $user->isAdmin();
    }

    public function remove(User $user, EquipmentConsumable $consumable): bool
    {
        return $this->userCanAccessWorkshop($user, $consumable->workshop_id)
            && $user->isAdmin();
    }
}
