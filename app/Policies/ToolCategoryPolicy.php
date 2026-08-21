<?php

namespace App\Policies;

use App\Models\ToolCategory;
use App\Models\User;

class ToolCategoryPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, ToolCategory $category): bool
    {
        return $this->userCanAccessWorkshop($user, $category->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ToolCategory $category): bool
    {
        return $this->userCanAccessWorkshop($user, $category->workshop_id)
            && $user->isAdmin();
    }

    public function delete(User $user, ToolCategory $category): bool
    {
        return $this->userCanAccessWorkshop($user, $category->workshop_id)
            && $user->isAdmin();
    }
}
