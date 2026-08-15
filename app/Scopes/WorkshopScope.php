<?php

namespace App\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that filters workshop-scoped tables by the current user's
 * workshop. Global admins (role=admin, workshop_id=null) see all rows.
 * Anyone else only sees rows for their own workshop; missing workshop
 * means "see nothing".
 *
 * Disable via WorkshopScope::disabled(...) when you need to read across
 * workshops (and you've already authorized the caller).
 */
class WorkshopScope implements Scope
{
    /**
     * Stack of disabled flags so nested calls behave correctly.
     *
     * @var array<int, true>
     */
    private static array $disabledStack = [];

    /**
     * Disable the scope for the duration of the given callback.
     */
    public static function disabled(callable $callback): mixed
    {
        self::$disabledStack[] = true;

        try {
            return $callback();
        } finally {
            array_pop(self::$disabledStack);
        }
    }

    public static function isDisabled(): bool
    {
        return count(self::$disabledStack) > 0;
    }

    public function apply(Builder $builder, Model $model): void
    {
        if (self::isDisabled()) {
            return;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            // No authenticated user — show nothing rather than leaking
            // cross-workshop data. Public pages must not use workshop-scoped
            // models directly.
            $builder->whereRaw('1 = 0');

            return;
        }

        if ($user->isGlobalAdmin()) {
            return;
        }

        if ($user->workshop_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.workshop_id', $user->workshop_id);
    }
}
