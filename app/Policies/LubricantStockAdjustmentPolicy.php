<?php

namespace App\Policies;

use App\Models\LubricantStockAdjustment;
use App\Models\User;

/**
 * Lubricant stock adjustments. Read for staff; create and approve/reject
 * for admins.
 */
class LubricantStockAdjustmentPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, LubricantStockAdjustment $adjustment): bool
    {
        return $this->userCanAccessWorkshop($user, $adjustment->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
