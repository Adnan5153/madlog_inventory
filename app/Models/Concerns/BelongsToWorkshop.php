<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\Workshop;
use App\Scopes\WorkshopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Applies a global scope so all workshop-scoped tables automatically
 * filter by the current user's workshop. Also exposes a helper to
 * disable the scope (e.g. inside service code that has already
 * authorized the operation).
 *
 * @property int $workshop_id
 */
trait BelongsToWorkshop
{
    /**
     * Boot the trait: register the global scope.
     */
    public static function bootBelongsToWorkshop(): void
    {
        static::addGlobalScope(new WorkshopScope);

        // Keep workshop_id in sync when the model is being saved without
        // an explicit value. Only fills when the column is null to avoid
        // clobbering a value the caller intentionally set (e.g. admin
        // creating a record for a different workshop).
        static::creating(function (Model $model) {
            if ($model->getAttribute('workshop_id') !== null) {
                return;
            }

            $user = Auth::user();
            if ($user instanceof User && $user->workshop_id !== null) {
                $model->setAttribute('workshop_id', $user->workshop_id);
            }
        });
    }

    /**
     * The workshop this row belongs to.
     */
    public function workshop()
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * Scope a query to a specific workshop. Bypasses the global scope's
     * auto-filter; callers are responsible for authorization.
     */
    public function scopeForWorkshop(Builder $query, int $workshopId): Builder
    {
        return $query->withoutGlobalScope(WorkshopScope::class)
            ->where($this->getTable().'.workshop_id', $workshopId);
    }

    /**
     * Scope a query to only include rows the current user is allowed to see.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isGlobalAdmin()) {
            return $query;
        }

        if ($user->workshop_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->getTable().'.workshop_id', $user->workshop_id);
    }

    /**
     * Run a callback with the workshop scope disabled. Useful inside
     * services that already verify authorization before reading data
     * outside the current user's workshop.
     */
    public static function withoutWorkshopScope(callable $callback)
    {
        return WorkshopScope::disabled(static function () use ($callback) {
            return $callback();
        });
    }
}
