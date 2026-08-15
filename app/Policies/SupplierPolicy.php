<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->userCanAccessWorkshop($user, $supplier->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->userCanAccessWorkshop($user, $supplier->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->userCanAccessWorkshop($user, $supplier->workshop_id)
            && $user->isAdmin();
    }
}
