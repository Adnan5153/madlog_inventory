<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Purchase orders: staff can draft and receive; only admins can approve.
 * Status transitions enforce this in the service layer.
 */
class PurchaseOrderPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $this->userCanAccessWorkshop($user, $po->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $this->userCanAccessWorkshop($user, $po->workshop_id)
            && $user->isAuthenticatedStaff();
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        // Only admins can delete; only on a PO that hasn't been received yet.
        return $this->userCanAccessWorkshop($user, $po->workshop_id)
            && $user->isAdmin()
            && ! $po->isFullyReceived();
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        return $this->userCanAccessWorkshop($user, $po->workshop_id)
            && $user->isAdmin();
    }

    public function receive(User $user, PurchaseOrder $po): bool
    {
        return $this->userCanAccessWorkshop($user, $po->workshop_id)
            && $user->isAuthenticatedStaff();
    }
}
