<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

/**
 * Stock movements are append-only — they cannot be updated or deleted.
 * Policies reflect that: only `view` and `create` are meaningful.
 * `create` is allowed for any authenticated staff member within the
 * workshop (after all, the InventoryService posts these entries on
 * behalf of every action).
 */
class StockMovementPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, StockMovement $movement): bool
    {
        return $this->userCanAccessWorkshop($user, $movement->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function update(User $user, StockMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, StockMovement $movement): bool
    {
        return false;
    }
}
