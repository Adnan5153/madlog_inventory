<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for Phase 2 (catalog & suppliers): product CRUD,
 * supplier CRUD, supplier categories, product import/export, reports.
 *
 * Verifies:
 *   - Product list/create/edit/destroy + audit log on writes
 *   - Product SKU uniqueness scoped to the workshop
 *   - Product import via CSV creates rows and respects headers
 *   - Product export returns CSV
 *   - Supplier CRUD with category
 *   - Reports endpoints return 200 and aggregate real data
 *   - Cross-tenant isolation (workshop A user cannot see workshop B records)
 */
class CatalogAndSuppliersTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected Workshop $workshop;
    protected Workshop $otherWorkshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->workshop = Workshop::factory()->create();
        $this->otherWorkshop = Workshop::factory()->create();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshop->id,
        ]);
    }

    public function test_admin_can_list_products(): void
    {
        Part::factory()->count(3)->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)->get('/admin/products');

        $response->assertOk();
        $response->assertSee('Products');
        $response->assertSee('Import CSV');
    }

    public function test_admin_can_create_product(): void
    {
        $category = PartCategory::factory()->create(['workshop_id' => $this->workshop->id]);
        $brand = Brand::factory()->create(['workshop_id' => $this->workshop->id]);
        $unit = Unit::factory()->create();

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Brake Pad',
                'sku' => 'BP-0001',
                'oem_part_number' => 'OEM-1',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'unit_id' => $unit->id,
                'cost_price' => 12.50,
                'sale_price' => 19.99,
                'reorder_threshold' => 5,
                'reorder_quantity' => 25,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->workshop->id,
            'name' => 'Brake Pad',
            'sku' => 'BP-0001',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'part.created',
        ]);
    }

    public function test_admin_can_update_product(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Old name',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/products/{$part->id}", [
                'name' => 'New name',
                'sku' => $part->sku,
                'cost_price' => $part->cost_price,
                'sale_price' => $part->sale_price,
                'reorder_threshold' => $part->reorder_threshold,
                'reorder_quantity' => $part->reorder_quantity,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $part->refresh();
        $this->assertSame('New name', $part->name);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'part.updated',
        ]);
    }

    public function test_product_sku_is_unique_per_workshop(): void
    {
        Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'SHARED-SKU',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Same SKU',
                'sku' => 'SHARED-SKU',
                'cost_price' => 1.00,
                'sale_price' => 2.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 1,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('sku');

        // Same SKU in another workshop is fine.
        Part::factory()->create([
            'workshop_id' => $this->otherWorkshop->id,
            'sku' => 'SHARED-SKU',
        ]);
    }

    public function test_product_with_inventory_cannot_be_deleted(): void
    {
        $part = Part::factory()->create(['workshop_id' => $this->workshop->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/products/{$part->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('parts', ['id' => $part->id]);
    }

    public function test_product_import_creates_rows(): void
    {
        $csv = implode("\n", [
            'sku,name,cost_price,sale_price,reorder_threshold,reorder_quantity',
            'IMP-1,Imported part 1,10,15,2,10',
            'IMP-2,Imported part 2,20,30,3,15',
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'parts-');
        file_put_contents($tmp, $csv);

        // Symfony's UploadedFile tries to guess MIME from fileinfo extension,
        // which isn't loaded in this environment. Pass a 'test' client mime.
        $uploaded = new \Illuminate\Http\UploadedFile($tmp, 'parts.csv', null, null, true);

        $this->actingAs($this->admin)
            ->post('/admin/products/import', ['file' => $uploaded])
            ->assertRedirect();

        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->workshop->id,
            'sku' => 'IMP-1',
            'name' => 'Imported part 1',
        ]);
        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->workshop->id,
            'sku' => 'IMP-2',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'parts.imported',
        ]);

        @unlink($tmp);
    }

    public function test_product_export_returns_csv(): void
    {
        Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'EXP-1',
            'name' => 'Export me',
            'cost_price' => 9.99,
            'sale_price' => 19.99,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/products-export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('EXP-1', $response->streamedContent());
        $this->assertStringContainsString('Export me', $response->streamedContent());
    }

    public function test_admin_can_crud_supplier_with_category(): void
    {
        $cat = SupplierCategory::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->post('/admin/suppliers', [
                'name' => 'Acme Parts',
                'contact_name' => 'Joe Smith',
                'email' => 'joe@acme.test',
                'phone' => '+1-555-0100',
                'tax_id' => 'TAX-123',
                'address' => '123 Acme Way',
                'notes' => 'Net-30 terms',
                'supplier_category_id' => $cat->id,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/suppliers');

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Acme Parts',
            'supplier_category_id' => $cat->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier.created',
        ]);
    }

    public function test_supplier_with_purchase_orders_cannot_be_deleted(): void
    {
        $supplier = Supplier::factory()->create(['workshop_id' => $this->workshop->id]);
        \App\Models\PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/suppliers/{$supplier->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('supplier');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id]);
    }

    public function test_admin_can_create_supplier_category(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/supplier-categories', [
                'name' => 'OEM',
                'description' => 'Original equipment manufacturers',
                'is_active' => true,
            ])
            ->assertRedirect('/admin/supplier-categories');

        $this->assertDatabaseHas('supplier_categories', [
            'name' => 'OEM',
            'code' => 'oem',
        ]);
    }

    public function test_inventory_valuation_report_returns_real_data(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'cost_price' => 5.00,
        ]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'quantity' => 10,
            'cost_price' => 5.00,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/reports/inventory-valuation');

        $response->assertOk();
        $response->assertSee('50.00'); // 10 * 5.00
    }

    public function test_low_stock_report_lists_parts_below_threshold(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Critical Brake Pad',
            'reorder_threshold' => 5,
        ]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'quantity' => 1, // below threshold
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/reports/low-stock');

        $response->assertOk();
        $response->assertSee('Critical Brake Pad');
    }

    public function test_movement_history_report_renders(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports/movement-history');
        $response->assertOk();
        $response->assertSee('Movement history');
    }

    public function test_top_consumed_report_renders(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/reports/top-consumed');
        $response->assertOk();
        $response->assertSee('Top consumed');
    }

    public function test_staff_cannot_create_products(): void
    {
        $this->actingAs($this->staff)
            ->post('/admin/products', [
                'name' => 'Forbidden',
                'cost_price' => 1,
                'sale_price' => 2,
                'reorder_threshold' => 1,
                'reorder_quantity' => 1,
                'is_active' => true,
            ])
            ->assertStatus(403);
    }

    public function test_staff_can_view_products(): void
    {
        Part::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->staff)
            ->get('/admin/products')
            ->assertOk();
    }

    public function test_cross_tenant_isolation(): void
    {
        // Product in workshop B.
        $partB = Part::factory()->create(['workshop_id' => $this->otherWorkshop->id]);

        // Admin of workshop A cannot edit workshop B's product.
        $this->actingAs($this->admin)
            ->get("/admin/products/{$partB->id}")
            ->assertStatus(404); // global scope hides it → 404
    }
}