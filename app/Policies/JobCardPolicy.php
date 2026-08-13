<?php

namespace App\Policies;

use App\Models\JobCard;
use App\Models\User;

/**
 * Job cards: anyone in the workshop can read; mechanics can update their
 * own; admins / managers can update anyone's.
 */
class JobCardPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function view(User $user, JobCard $jobCard): bool
    {
        return $this->userCanAccessWorkshop($user, $jobCard->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAuthenticatedStaff();
    }

    public function update(User $user, JobCard $jobCard): bool
    {
        if (! $this->userCanAccessWorkshop($user, $jobCard->workshop_id)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Staff can only edit cards they're the mechanic on.
        return $user->isStaff() && $jobCard->mechanic_id === $user->getKey();
    }

    public function delete(User $user, JobCard $jobCard): bool
    {
        return $this->userCanAccessWorkshop($user, $jobCard->workshop_id)
            && $user->isAdmin();
    }
}
