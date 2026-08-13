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
 * Feature coverage for /admin/roles:
 *
 * - Admin can create, edit, delete custom roles.
 * - System roles cannot be deleted.
 * - Permission sync happens on create/update.
 * - Permission catalogue is browsable.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => Workshop::factory(),
        ]);
    }

    public function test_admin_can_create_custom_role(): void
    {
        $perm = \App\Models\Permission::query()->where('name', 'products.view')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/admin/roles', [
                'name' => 'Product Reader',
                'slug' => 'product-reader',
                'description' => 'Read-only product access',
                'permissions' => [$perm->id],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('slug', 'product-reader')->firstOrFail();
        $this->assertSame('Product Reader', $role->name);
        $this->assertFalse($role->is_system);
        $this->assertSame(1, $role->permissions()->count());
        $this->assertSame('products.view', $role->permissions()->first()->name);
    }

    public function test_admin_can_update_role_and_replace_permissions(): void
    {
        $role = Role::create([
            'name' => 'Limited',
            'slug' => 'limited',
            'description' => 'Limited',
            'is_system' => false,
        ]);

        $a = \App\Models\Permission::query()->where('name', 'products.view')->firstOrFail();
        $b = \App\Models\Permission::query()->where('name', 'reports.view')->firstOrFail();
        $c = \App\Models\Permission::query()->where('name', 'audit-logs.view')->firstOrFail();
        $role->syncPermissions([$a->id, $b->id]);

        $this->actingAs($this->admin)
            ->put("/admin/roles/{$role->id}", [
                'name' => 'Limited',
                'slug' => 'limited',
                'description' => 'Limited — audit only',
                'permissions' => [$c->id],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $fresh = $role->fresh();
        $this->assertSame(1, $fresh->permissions()->count());
        $this->assertSame('audit-logs.view', $fresh->permissions()->first()->name);
    }

    public function test_admin_can_delete_custom_role(): void
    {
        $role = Role::create([
            'name' => 'Disposable',
            'slug' => 'disposable',
            'description' => 'To be deleted',
            'is_system' => false,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/roles/{$role->id}")
            ->assertRedirect(route('admin.roles.index'));

        $this->assertNull(Role::query()->find($role->id));
    }

    public function test_admin_cannot_delete_system_role(): void
    {
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete("/admin/roles/{$role->id}")
            ->assertForbidden();

        $this->assertNotNull(Role::query()->find($role->id));
    }

    public function test_admin_can_browse_permission_catalogue(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/permissions')
            ->assertOk()
            ->assertSee('products.view')
            ->assertSee('roles.manage');
    }

    public function test_admin_can_view_a_single_permission(): void
    {
        $perm = \App\Models\Permission::query()->where('name', 'products.view')->firstOrFail();

        $this->actingAs($this->admin)
            ->get("/admin/permissions/{$perm->id}")
            ->assertOk()
            ->assertSee('products.view');
    }

    public function test_creating_role_with_invalid_permission_id_fails_validation(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/roles', [
                'name' => 'Bad',
                'slug' => 'bad',
                'description' => null,
                'permissions' => [99999], // non-existent
            ])
            ->assertSessionHasErrors('permissions.0');
    }

    public function test_creating_role_with_invalid_slug_fails_validation(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/roles', [
                'name' => 'Bad Slug',
                'slug' => 'Bad Slug With Spaces',
                'description' => null,
                'permissions' => [],
            ])
            ->assertSessionHasErrors('slug');
    }
}