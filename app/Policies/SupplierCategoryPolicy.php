<?php

namespace App\Policies;

use App\Models\SupplierCategory;
use App\Models\User;

class SupplierCategoryPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, SupplierCategory $category): bool
    {
        return $this->userCanAccessWorkshop($user, $category->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SupplierCategory $category): bool
    {
        return $this->userCanAccessWorkshop($user, $category->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, SupplierCategory $category): bool
    {
        return $this->userCanAccessWorkshop($user, $category->workshop_id)
            && $user->isAdmin();
    }
}
