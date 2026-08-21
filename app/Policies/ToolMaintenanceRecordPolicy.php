<?php

namespace App\Policies;

use App\Models\ToolMaintenanceRecord;
use App\Models\User;

/**
 * Tool maintenance history. Admin handles create/update/delete; staff
 * may read the ledger to see when the next service is due.
 */
class ToolMaintenanceRecordPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, ToolMaintenanceRecord $record): bool
    {
        return $this->userCanAccessWorkshop($user, $record->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ToolMaintenanceRecord $record): bool
    {
        return $this->userCanAccessWorkshop($user, $record->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, ToolMaintenanceRecord $record): bool
    {
        return $this->userCanAccessWorkshop($user, $record->workshop_id)
            && $user->isAdmin();
    }
}
