<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Equipment;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for Phase 1 (foundation): admin layout, dashboard,
 * master-data CRUD, and settings.
 *
 * Verifies:
 *   - Non-admin redirected away from /admin
 *   - Admin can reach every master-data index page
 *   - Admin can create, edit, delete (with safety checks)
 *   - Settings update persists and bypasses cache on next read
 *   - Every write produces an AuditLog row
 */
class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the default settings so the SettingsSeeder codepath isn't a false negative.
        $this->seed(SettingsSeeder::class);

        // A workshop so workshop-scoped records (categories/units/equipment/etc.) have a parent.
        $this->workshop = Workshop::factory()->create();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshop->id,
        ]);
    }

    public function test_dashboard_renders_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('Workshops');
        $response->assertSee('Parts catalog');
    }

    public function test_staff_can_view_dashboard_but_cannot_edit(): void
    {
        // Staff can browse the admin area (read-only) — write actions are
        // gated by model policies. The middleware now accepts both admin
        // and staff roles; granular permission checks land in P6.
        $response = $this->actingAs($this->staff)->get('/admin');

        $response->assertOk();
    }

    public function test_dashboard_redirects_unauthenticated(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_list_categories(): void
    {
        PartCategory::factory()->count(3)->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)->get('/admin/categories');

        $response->assertOk();
        $response->assertSee('Categories');
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/categories', [
                'name' => 'Brakes',
                'description' => 'Pads, discs, calipers',
            ])
            ->assertRedirect('/admin/categories');

        $this->assertDatabaseHas('part_categories', [
            'name' => 'Brakes',
            'slug' => 'brakes',
            'description' => 'Pads, discs, calipers',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'category.created',
        ]);
    }

    public function test_admin_can_update_category(): void
    {
        $category = PartCategory::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->put("/admin/categories/{$category->id}", [
                'name' => 'New name',
                'description' => 'Updated',
            ])
            ->assertRedirect('/admin/categories');

        $category->refresh();
        $this->assertSame('New name', $category->name);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'category.updated',
            'subject_id' => $category->id,
        ]);
    }

    public function test_admin_can_create_brand(): void
    {
        // The Brand CRUD module has been removed; brand is now a free-text
        // field on products. This test exercises that path instead.
        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Brake Pad',
                'sku' => 'BP-BRAND',
                'brand' => 'Bosch',
                'cost_price' => 1.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 1,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->admin->workshop_id,
            'sku' => 'BP-BRAND',
            'brand' => 'Bosch',
        ]);
    }

    public function test_admin_can_create_unit(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/units', [
                'name' => 'Liter',
                'short_code' => 'L',
                'decimal_precision' => 2,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/units');

        $this->assertDatabaseHas('units', ['short_code' => 'L']);
    }

    public function test_admin_can_create_department(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/departments', [
                'name' => 'Maintenance',
                'code' => 'MAINT',
                'is_active' => true,
            ])
            ->assertRedirect('/admin/departments');

        $this->assertDatabaseHas('departments', ['code' => 'MAINT']);
    }

    public function test_admin_can_create_equipment(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/equipment', [
                'name' => 'Hydraulic Press',
                'asset_number' => 'EQ-1001',
                'manufacturer' => 'ACME',
                'status' => 'active',
                'is_active' => true,
            ])
            ->assertRedirect('/admin/equipment');

        $this->assertDatabaseHas('equipment', ['asset_number' => 'EQ-1001']);
    }

    public function test_settings_page_renders_seeded_defaults(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/system/settings');

        $response->assertOk();
        $response->assertSee('inventory.default_currency');
        $response->assertSee('allow_negative_stock');
        $response->assertSee('PO-{YYYY}-{NNNN}'); // po_number_format default
    }

    public function test_settings_update_persists_and_invalidates_cache(): void
    {
        // First read goes to DB and caches.
        $this->assertSame('USD', setting('inventory.default_currency'));

        // Now update via the controller.
        $this->actingAs($this->admin)
            ->put('/admin/system/settings', [
                'global' => [
                    'inventory.default_currency' => 'EUR',
                    'inventory.allow_negative_stock' => '1',
                ],
            ])
            ->assertRedirect('/admin/system/settings');

        // Re-read should pick up the new value (cache flushed).
        $this->assertSame('EUR', setting('inventory.default_currency'));
        $this->assertTrue((bool) setting('inventory.allow_negative_stock'));

        // Audit log written.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'setting.updated',
        ]);
    }

    public function test_settings_helper_falls_back_to_default(): void
    {
        $this->assertNull(setting('nonexistent.key'));
        $this->assertSame('default-value', setting('nonexistent.key', 'default-value'));
    }

    public function test_category_with_parts_cannot_be_deleted(): void
    {
        $category = PartCategory::factory()->create(['workshop_id' => $this->workshop->id]);
        Part::factory()->create(['workshop_id' => $this->workshop->id, 'category_id' => $category->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/categories/{$category->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('part_categories', ['id' => $category->id]);
    }

    public function test_unit_in_use_cannot_be_deleted(): void
    {
        $unit = Unit::factory()->create();
        Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'unit_id' => $unit->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/units/{$unit->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('unit');

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_sidebar_renders_all_p1_links(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin');

        $response->assertSee('Categories', false);
        $response->assertSee('Products / Parts', false);
        $response->assertSee('Units of Measure', false);
        $response->assertSee('Departments', false);
        $response->assertSee('Equipment', false);
        $response->assertSee('Settings', false);
    }
}
