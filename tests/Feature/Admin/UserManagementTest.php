<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for /admin/users CRUD:
 *
 * - Admin can list/create/edit/delete users.
 * - Non-admin (staff) cannot access the page (admin middleware).
 * - Self-edit allowed; self-delete blocked.
 * - RBAC roles can be assigned at create/update.
 * - Cross-workshop admin can edit users in their own workshop.
 * - Role sync happens on update.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->workshop = Workshop::factory()->create();
        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);
    }

    public function test_admin_can_view_users_index(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Users');
    }

    public function test_admin_can_create_user_with_rbac_role(): void
    {
        $auditor = Role::query()->where('slug', 'auditor')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/users', [
                'name' => 'Auditor Jo',
                'email' => 'jo@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
                'role' => User::ROLE_STAFF,
                'workshop_id' => $this->workshop->id,
                'rbac_roles' => [$auditor->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'jo@example.com')->firstOrFail();
        $this->assertTrue($created->rbacRoles->contains('id', $auditor->id));
    }

    public function test_admin_can_update_user_and_change_roles(): void
    {
        $auditor = Role::query()->where('slug', 'auditor')->firstOrFail();
        $inventoryManager = Role::query()->where('slug', 'inventory-manager')->firstOrFail();

        $subject = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshop->id,
        ]);
        $subject->assignRole($auditor);

        $this->actingAs($this->admin)
            ->put("/admin/users/{$subject->id}", [
                'name' => $subject->name,
                'email' => $subject->email,
                'role' => User::ROLE_STAFF,
                'workshop_id' => $this->workshop->id,
                'rbac_roles' => [$inventoryManager->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $fresh = $subject->fresh();
        $this->assertFalse($fresh->rbacRoles->contains('id', $auditor->id));
        $this->assertTrue($fresh->rbacRoles->contains('id', $inventoryManager->id));
    }

    public function test_admin_can_delete_other_user(): void
    {
        $other = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/users/{$other->id}")
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::query()->find($other->id));
        $this->assertSoftDeleted('users', ['id' => $other->id]);
    }

    public function test_staff_cannot_access_users_page(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->actingAs($staff)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_user_can_edit_their_own_profile(): void
    {
        // The admin updates their own profile — should be allowed.
        $this->actingAs($this->admin)
            ->put("/admin/users/{$this->admin->id}", [
                'name' => 'Renamed Admin',
                'email' => $this->admin->email,
                'role' => User::ROLE_ADMIN,
                'workshop_id' => null, // global admin
                'rbac_roles' => [],
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('Renamed Admin', $this->admin->fresh()->name);
    }

    public function test_admin_cannot_self_delete(): void
    {
        // Policy blocks self-delete (returns false from delete ability).
        // The HTTP layer then returns 403 because authorization failed.
        $this->actingAs($this->admin)
            ->delete("/admin/users/{$this->admin->id}")
            ->assertForbidden();

        $this->assertNull($this->admin->fresh()->deleted_at);
    }
}