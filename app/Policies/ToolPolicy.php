<?php

namespace App\Policies;

use App\Models\Tool;
use App\Models\User;

/**
 * Tools: admin-only create/update/delete. Authenticated staff can read
 * the catalog and check tools in/out via the lifecycle methods below.
 */
class ToolPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, Tool $tool): bool
    {
        return $this->userCanAccessWorkshop($user, $tool->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Tool $tool): bool
    {
        return $this->userCanAccessWorkshop($user, $tool->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, Tool $tool): bool
    {
        return $this->userCanAccessWorkshop($user, $tool->workshop_id)
            && $user->isAdmin();
    }

    public function checkout(User $user, ?Tool $tool = null): bool
    {
        if ($tool === null) {
            return $user->isAuthenticatedStaff();
        }

        return $this->userCanAccessWorkshop($user, $tool->workshop_id)
            && $user->isAuthenticatedStaff();
    }

    public function checkin(User $user, ?Tool $tool = null): bool
    {
        if ($tool === null) {
            return $user->isAuthenticatedStaff();
        }

        return $this->userCanAccessWorkshop($user, $tool->workshop_id)
            && $user->isAuthenticatedStaff();
    }

    public function recordMaintenance(User $user, ?Tool $tool = null): bool
    {
        if ($tool === null) {
            return $user->isAdmin();
        }

        return $this->userCanAccessWorkshop($user, $tool->workshop_id)
            && $user->isAdmin();
    }
}
