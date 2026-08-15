<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use App\Policies\RolePolicy;
use App\Services\Access\RolePermissionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RBAC layer coverage:
 *
 * - Admin role grants every permission (super-admin fast-path).
 * - Non-admin users with a Role inherit its permissions.
 * - Direct permission grants (with optional expiry) are honoured.
 * - Expired direct grants are skipped.
 * - Custom roles can be created and synced.
 * - System roles cannot be deleted.
 */
class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_role_grants_every_permission(): void
    {
        $ws = Workshop::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $ws->id,
        ]);

        $this->assertTrue($admin->hasPermission('products.view'));
        $this->assertTrue($admin->hasPermission('roles.manage'));
        $this->assertTrue($admin->hasPermission('settings.manage'));
        $this->assertTrue($admin->hasPermission('completely.fictional.ability'));
    }

    public function test_staff_with_role_inherits_role_permissions(): void
    {
        $ws = Workshop::factory()->create();
        $auditor = Role::query()->where('slug', 'auditor')->firstOrFail();
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $ws->id,
        ]);
        $user->assignRole($auditor);

        $this->assertTrue($user->hasPermission('audit-logs.view'));
        $this->assertTrue($user->hasPermission('reports.view'));
        $this->assertFalse($user->hasPermission('users.manage'));
    }

    public function test_direct_grants_extend_role_permissions(): void
    {
        $ws = Workshop::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $ws->id,
        ]);

        $auditor = Role::query()->where('slug', 'auditor')->firstOrFail();
        $staff->assignRole($auditor);

        $usersManage = Permission::query()->where('name', 'users.manage')->firstOrFail();
        $staff->givePermissionTo($usersManage);

        $this->assertTrue($staff->hasPermission('audit-logs.view'));
        $this->assertTrue($staff->hasPermission('users.manage'));
    }

    public function test_expired_direct_grants_are_ignored(): void
    {
        $ws = Workshop::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $ws->id,
        ]);

        $perm = Permission::query()->where('name', 'users.manage')->firstOrFail();
        $staff->givePermissionTo($perm, now()->subDay());

        $this->assertFalse($staff->hasPermission('users.manage'));
    }

    public function test_unexpired_direct_grants_are_honoured(): void
    {
        $ws = Workshop::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $ws->id,
        ]);

        $perm = Permission::query()->where('name', 'users.manage')->firstOrFail();
        $staff->givePermissionTo($perm, now()->addDay());

        $this->assertTrue($staff->hasPermission('users.manage'));
    }

    public function test_user_without_role_or_grants_has_no_permissions(): void
    {
        $ws = Workshop::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $ws->id,
        ]);

        $this->assertFalse($staff->hasPermission('products.view'));
    }

    public function test_role_sync_replaces_permission_set(): void
    {
        $rbac = app(RolePermissionService::class);
        $role = $rbac->ensureRole('Limited', 'limited', 'A narrow role');

        $this->assertSame(0, $role->permissions()->count());

        $permA = Permission::query()->where('name', 'products.view')->firstOrFail();
        $permB = Permission::query()->where('name', 'reports.view')->firstOrFail();
        $role->syncPermissions([$permA->id, $permB->id]);
        $this->assertSame(2, $role->permissions()->count());

        $permC = Permission::query()->where('name', 'audit-logs.view')->firstOrFail();
        $role->syncPermissions([$permC->id]);
        $this->assertSame(1, $role->permissions()->count());
        $this->assertSame('audit-logs.view', $role->permissions()->first()->name);
    }

    public function test_system_role_policy_denies_delete(): void
    {
        $superAdmin = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $this->assertTrue($superAdmin->is_system);

        $policy = new RolePolicy;

        // The policy returns false for system roles without consulting
        // the caller's permission set. We verify the policy logic with
        // a fresh staff user (no admin role; the super-admin fast-path
        // is irrelevant here).
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => Workshop::factory(),
        ]);

        // The policy's delete method explicitly returns false for
        // is_system roles; the rest of the logic only checks
        // roles.manage. Even with roles.manage granted, is_system blocks.
        $staff->givePermissionTo(Permission::query()->where('name', 'roles.manage')->firstOrFail());

        $this->assertFalse($policy->delete($staff, $superAdmin));

        // Non-system roles are deletable when the caller has roles.manage.
        $custom = Role::create([
            'name' => 'Custom',
            'slug' => 'custom-rbac-test',
            'description' => null,
            'is_system' => false,
        ]);
        $this->assertTrue($policy->delete($staff, $custom));
    }
}
