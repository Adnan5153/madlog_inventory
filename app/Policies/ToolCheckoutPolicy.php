<?php

namespace App\Policies;

use App\Models\ToolCheckout;
use App\Models\User;

/**
 * Tool checkout ledger rows. Read tied to workshop; admin-only delete
 * (soft) so the historical ledger stays intact by default.
 */
class ToolCheckoutPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, ToolCheckout $checkout): bool
    {
        return $this->userCanAccessWorkshop($user, $checkout->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function update(User $user, ToolCheckout $checkout): bool
    {
        return $this->userCanAccessWorkshop($user, $checkout->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, ToolCheckout $checkout): bool
    {
        return $this->userCanAccessWorkshop($user, $checkout->workshop_id)
            && $user->isAdmin();
    }
}
