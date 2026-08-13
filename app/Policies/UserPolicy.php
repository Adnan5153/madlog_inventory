<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management. Creating users / changing roles is admin-only.
 * Users can read their own profile and the profiles of users in their
 * own workshop.
 */
class UserPolicy extends WorkshopScopedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $viewer, User $subject): bool
    {
        return $this->userCanAccessWorkshop($viewer, $subject->workshop_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $actor, User $subject): bool
    {
        // Self-edit is allowed (profile updates, password, etc.).
        if ($actor->is($subject)) {
            return true;
        }

        return $this->userCanAccessWorkshop($actor, $subject->workshop_id)
            && $actor->isAdmin();
    }

    public function delete(User $actor, User $subject): bool
    {
        if ($actor->is($subject)) {
            return false;
        }

        return $this->userCanAccessWorkshop($actor, $subject->workshop_id)
            && $actor->isAdmin();
    }
}
