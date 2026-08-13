<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A named bundle of permissions (e.g. "Inventory Manager").
 *
 * Roles are the primary way to assign a coherent set of abilities to
 * a user; permissions can also be granted directly via the
 * `permission_user` pivot. Built-in roles are flagged `is_system=true`
 * and the UI refuses to delete them.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_system
 */
#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role')
            ->withTimestamps();
    }

    /**
     * Replace this role's permission set with the given permission IDs.
     * Caller is responsible for wrapping in a transaction.
     *
     * @param  array<int>  $permissionIds
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    public function grant(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    public function revoke(Permission $permission): void
    {
        $this->permissions()->detach($permission->id);
    }
}