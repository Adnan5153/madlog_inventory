<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $role
 * @property int|null $workshop_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Workshop|null $workshop
 */
#[Fillable(['name', 'email', 'password', 'role', 'workshop_id', 'email_verified_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, SoftDeletes;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_STAFF = 'staff';

    /**
     * Role values that this application recognizes.
     * Anything else (including null) means the user is treated as a public visitor.
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return [self::ROLE_ADMIN, self::ROLE_STAFF];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials.
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1) . Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Workshop this user belongs to (null for global admins).
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * Stock movements recorded by this user.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Tool checkouts issued to this user.
     */
    public function toolCheckouts(): HasMany
    {
        return $this->hasMany(ToolCheckout::class);
    }

    /**
     * Purchase orders created by this user.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'created_by');
    }

    /**
     * Authorization helpers — single source of truth for role checks.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isAuthenticatedStaff(): bool
    {
        return $this->isAdmin() || $this->isStaff();
    }

    /**
     * Global admins have no workshop; workshop-scoped admins have one.
     */
    public function isGlobalAdmin(): bool
    {
        return $this->isAdmin() && $this->workshop_id === null;
    }

    /**
     * Users who belong to no workshop cannot see workshop-scoped data,
     * unless they are global admins.
     */
    public function canAccessWorkshop(?int $workshopId): bool
    {
        if ($this->isGlobalAdmin()) {
            return true;
        }

        return $workshopId !== null && $this->workshop_id === $workshopId;
    }

    // ---------------------------------------------------------------
    // RBAC (Phase 6)
    // ---------------------------------------------------------------

    /**
     * Roles assigned to this user via `role_user`. Named `rbacRoles`
     * to avoid colliding with the `roles()` static factory on User.
     */
    public function rbacRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Permissions granted directly via `permission_user`.
     */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withPivot('expires_at')
            ->withTimestamps();
    }

    /**
     * Per-request cache of resolved permission names. Populated lazily
     * by `hasPermission()`; the registrar clears it across requests by
     * never caching to a static store beyond the instance.
     *
     * @var Collection<int, string>|null
     */
    protected ?Collection $cachedPermissions = null;

    /**
     * Determine whether the user has the named permission.
     *
     * Fast-path: `users.role === admin` grants `super-admin`.
     * Otherwise: union of role permissions + direct grants (excluding
     * expired direct grants), cached on this instance for the request.
     */
    public function hasPermission(string $name): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = $this->loadPermissions();
        }

        return $this->cachedPermissions->contains($name);
    }

    /**
     * Resolve the set of effective permission names for this user.
     *
     * @return Collection<int, string>
     */
    public function loadPermissions(): Collection
    {
        $now = now();

        // From roles
        $viaRole = $this->rbacRoles()
            ->with('permissions:id,name')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name');

        // Direct grants, skipping expired rows
        $direct = $this->directPermissions()
            ->wherePivot('expires_at', null)
            ->orWherePivot('expires_at', '>', $now)
            ->get()
            ->pluck('name');

        return collect($viaRole)->merge($direct)->unique()->values();
    }

    /**
     * Clear the in-memory permissions cache (mostly useful in tests).
     */
    public function flushPermissionCache(): void
    {
        $this->cachedPermissions = null;
    }

    /**
     * Assign a role (no-op if already attached).
     */
    public function assignRole(Role $role): void
    {
        $this->rbacRoles()->syncWithoutDetaching([$role->id]);
        $this->flushPermissionCache();
    }

    /**
     * Revoke a role.
     */
    public function removeRole(Role $role): void
    {
        $this->rbacRoles()->detach($role->id);
        $this->flushPermissionCache();
    }

    /**
     * Grant a permission directly to this user.
     */
    public function givePermissionTo(Permission $permission, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->directPermissions()->attach($permission->id, [
            'expires_at' => $expiresAt,
        ]);
        $this->flushPermissionCache();
    }

    /**
     * Revoke a direct permission grant.
     */
    public function revokePermission(Permission $permission): void
    {
        $this->directPermissions()->detach($permission->id);
        $this->flushPermissionCache();
    }
}
