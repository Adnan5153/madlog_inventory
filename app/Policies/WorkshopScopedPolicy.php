<?php

namespace App\Policies;

use App\Models\User;

/**
 * Shared workshop-scope check used by every concrete policy.
 *
 * Returns true when the user is allowed to touch a row from
 * `$workshopId`. Centralized so the policy classes stay declarative
 * (one line per ability) and the cross-cutting rule lives in one place.
 */
class WorkshopScopedPolicy
{
    /**
     * Check whether the given user can act on a row belonging to
     * `$workshopId`. Returns false for unauthenticated callers.
     */
    protected function userCanAccessWorkshop(?User $user, ?int $workshopId): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $workshopId !== null && $user->workshop_id === $workshopId;
    }
}
