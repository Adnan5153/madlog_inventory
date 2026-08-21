<?php

namespace App\Policies;

use App\Models\BatteryStockAdjustment;
use App\Models\User;

/**
 * Battery stock adjustments. Read for staff; create and approve/reject
 * for admins.
 */
class BatteryStockAdjustmentPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, BatteryStockAdjustment $adjustment): bool
    {
        return $this->userCanAccessWorkshop($user, $adjustment->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }
}
