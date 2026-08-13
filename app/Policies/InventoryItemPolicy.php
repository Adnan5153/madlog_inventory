<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;

/**
 * Inventory items represent the on-hand quantity in a bin/batch.
 * Staff can read; admins can adjust (which produces a stock_movement
 * ledger entry via InventoryService).
 */
class InventoryItemPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $this->userCanAccessWorkshop($user, $item->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, InventoryItem $item): bool
    {
        // Both admins and staff can adjust stock (a storekeeper counts
        // and corrects), but the change must be authorized within
        // their workshop.
        return $this->userCanAccessWorkshop($user, $item->workshop_id)
            && $user->isAuthenticatedStaff();
    }

    public function delete(User $user, InventoryItem $item): bool
    {
        return $this->userCanAccessWorkshop($user, $item->workshop_id)
            && $user->isAdmin();
    }
}
