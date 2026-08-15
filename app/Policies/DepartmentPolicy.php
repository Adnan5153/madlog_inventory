<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Department $department): bool
    {
        return $this->userCanAccessWorkshop($user, $department->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Department $department): bool
    {
        return $this->userCanAccessWorkshop($user, $department->workshop_id);
    }

    public function delete(User $user, Department $department): bool
    {
        return $this->userCanAccessWorkshop($user, $department->workshop_id)
            && $department->equipment()->doesntExist();
    }
}
