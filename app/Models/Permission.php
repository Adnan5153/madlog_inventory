<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single named ability, e.g. "products.view", "inventory.adjust".
 *
 * Permission names are stored in dotted notation grouped by domain
 * (products.*, warehouses.*, inventory.*, etc.). They're registered
 * as Laravel Gates in `PermissionRegistrar` and as middleware via
 * the `permission` route alias.
 *
 * @property int $id
 * @property string $name
 * @property string $group
 * @property string|null $description
 */
#[Fillable(['name', 'group', 'description'])]
class Permission extends Model
{
    /**
     * Permissions that belong to a "super-admin" effectively grant
     * every ability in the system. Mirrors Laravel's Gate::before()
     * super-admin convention.
     */
    public const SUPER_ADMIN = 'super-admin';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user')
            ->withPivot('expires_at')
            ->withTimestamps();
    }

    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeInGroup(Builder $query, string ...$groups): Builder
    {
        return $query->whereIn('group', $groups);
    }
}
