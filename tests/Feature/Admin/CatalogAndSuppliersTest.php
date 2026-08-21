<?php

namespace Tests\Feature\Admin;

use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $this->seed(SettingsSeeder::class);

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
        $unit = Unit::factory()->create();

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Brake Pad',
                'sku' => 'BP-0001',
                'oem_part_number' => 'OEM-1',
                'category_id' => $category->id,
                'brand' => 'Bosch',
                'unit_id' => $unit->id,
                'cost_price' => 12.50,
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
            'sku,name,cost_price,reorder_threshold,reorder_quantity',
            'IMP-1,Imported part 1,10,2,10',
            'IMP-2,Imported part 2,20,3,15',
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'parts-');
        file_put_contents($tmp, $csv);

        // Symfony's UploadedFile tries to guess MIME from fileinfo extension,
        // which isn't loaded in this environment. Pass a 'test' client mime.
        $uploaded = new UploadedFile($tmp, 'parts.csv', null, null, true);

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
        PurchaseOrder::factory()->create([
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

    public function test_admin_can_create_product_with_bin_location(): void
    {
        $bin = BinLocation::factory()->create([
            'workshop_id' => $this->workshop->id,
            'code' => 'BIN-001',
            'zone' => 'Z',
            'aisle' => 'A',
            'shelf' => '3',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Stored in bin',
                'sku' => 'BIN-STORED',
                'bin_location_id' => $bin->id,
                'cost_price' => 5.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->workshop->id,
            'sku' => 'BIN-STORED',
            'bin_location_id' => $bin->id,
            'location' => null,
        ]);

        $part = Part::where('sku', 'BIN-STORED')->first();
        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('BIN-001')
            ->assertSee('Z');
    }

    public function test_admin_can_create_product_with_custom_location(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Stored in almirah',
                'sku' => 'ALMIRAH-1',
                'location' => 'Almirah #4, Shelf 3',
                'cost_price' => 5.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->workshop->id,
            'sku' => 'ALMIRAH-1',
            'bin_location_id' => null,
            'location' => 'Almirah #4, Shelf 3',
        ]);

        $part = Part::where('sku', 'ALMIRAH-1')->first();
        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Almirah #4, Shelf 3');
    }

    public function test_admin_can_create_product_with_both_bin_and_custom_location(): void
    {
        $bin = BinLocation::factory()->create([
            'workshop_id' => $this->workshop->id,
            'code' => 'BIN-002',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Both set',
                'sku' => 'BOTH-1',
                'bin_location_id' => $bin->id,
                'location' => 'CUSTOM-WINS',
                'cost_price' => 5.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $part = Part::where('sku', 'BOTH-1')->first();
        $this->assertNotNull($part->bin_location_id);
        $this->assertSame('CUSTOM-WINS', $part->location);

        // Free-text location wins in display precedence.
        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('CUSTOM-WINS');
    }

    public function test_admin_can_update_product_storage(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'bin_location_id' => null,
            'location' => null,
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/products/{$part->id}", [
                'name' => $part->name,
                'sku' => $part->sku,
                'cost_price' => $part->cost_price,
                'reorder_threshold' => $part->reorder_threshold,
                'reorder_quantity' => $part->reorder_quantity,
                'is_active' => true,
                'location' => 'Off-site safe',
            ])
            ->assertRedirect('/admin/products');

        $part->refresh();
        $this->assertSame('Off-site safe', $part->location);
    }

    public function test_validation_rejects_bin_from_other_workshop(): void
    {
        // Bin belonging to the OTHER workshop.
        $foreignBin = BinLocation::factory()->create([
            'workshop_id' => $this->otherWorkshop->id,
            'code' => 'FOREIGN-BIN',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Cross-tenant attempt',
                'bin_location_id' => $foreignBin->id,
                'cost_price' => 5.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('bin_location_id');

        $this->assertDatabaseMissing('parts', [
            'name' => 'Cross-tenant attempt',
        ]);
    }

    public function test_search_finds_product_by_location_string(): void
    {
        Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Searchable by location',
            'location' => 'Almirah #4, Shelf 3',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=Almirah');

        $response->assertOk();
        $response->assertSee('Searchable by location');
    }

    public function test_search_finds_product_by_bin_code(): void
    {
        $bin = BinLocation::factory()->create([
            'workshop_id' => $this->workshop->id,
            'code' => 'STEEL-A1',
        ]);

        Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Lives in steel bin',
            'bin_location_id' => $bin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=STEEL-A1');

        $response->assertOk();
        $response->assertSee('Lives in steel bin');
    }

    public function test_admin_can_create_product_with_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Acme Parts',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Sourced from Acme',
                'sku' => 'ACME-1',
                'supplier_id' => $supplier->id,
                'cost_price' => 5.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->workshop->id,
            'sku' => 'ACME-1',
            'supplier_id' => $supplier->id,
        ]);

        $part = Part::where('sku', 'ACME-1')->first();
        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Acme Parts');
    }

    public function test_admin_can_create_product_without_supplier(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'No supplier here',
                'sku' => 'NOSUP-1',
                'cost_price' => 5.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'sku' => 'NOSUP-1',
            'supplier_id' => null,
        ]);

        $part = Part::where('sku', 'NOSUP-1')->first();
        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Supplier')
            ->assertSee('—');
    }

    public function test_admin_can_update_product_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'New Vendor',
        ]);

        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => null,
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/products/{$part->id}", [
                'name' => $part->name,
                'sku' => $part->sku,
                'cost_price' => $part->cost_price,
                'reorder_threshold' => $part->reorder_threshold,
                'reorder_quantity' => $part->reorder_quantity,
                'is_active' => true,
                'supplier_id' => $supplier->id,
            ])
            ->assertRedirect('/admin/products');

        $part->refresh();
        $this->assertSame($supplier->id, $part->supplier_id);
    }

    public function test_admin_can_clear_supplier_on_update(): void
    {
        $supplier = Supplier::factory()->create(['workshop_id' => $this->workshop->id]);
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/products/{$part->id}", [
                'name' => $part->name,
                'sku' => $part->sku,
                'cost_price' => $part->cost_price,
                'reorder_threshold' => $part->reorder_threshold,
                'reorder_quantity' => $part->reorder_quantity,
                'is_active' => true,
                'supplier_id' => null,
            ])
            ->assertRedirect('/admin/products');

        $part->refresh();
        $this->assertNull($part->supplier_id);
    }

    public function test_validation_rejects_supplier_from_other_workshop(): void
    {
        $foreignSupplier = Supplier::factory()->create([
            'workshop_id' => $this->otherWorkshop->id,
            'name' => 'Foreign Vendor',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Cross-tenant supplier attempt',
                'supplier_id' => $foreignSupplier->id,
                'cost_price' => 5.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseMissing('parts', [
            'name' => 'Cross-tenant supplier attempt',
        ]);
    }

    public function test_show_page_renders_supplier_label_and_name(): void
    {
        $supplier = Supplier::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Show Supplier Co',
        ]);
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Supplier')
            ->assertSee('Show Supplier Co');
    }

    public function test_index_page_does_not_render_supplier_name(): void
    {
        $uniqueName = 'ZZZ-UNIQUE-SUPPLIER-'.uniqid();
        $supplier = Supplier::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => $uniqueName,
        ]);
        Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Product with hidden supplier',
            'supplier_id' => $supplier->id,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('Product with hidden supplier')
            ->assertDontSee($uniqueName);
    }

    public function test_admin_can_create_product_with_equipment_compatibility(): void
    {
        $compat = "Toyota Corolla 2014–2018\nHonda Civic 2016+";

        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Brake disc rotor',
                'sku' => 'COMPAT-1',
                'equipment_compatibility' => $compat,
                'cost_price' => 25.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'workshop_id' => $this->workshop->id,
            'sku' => 'COMPAT-1',
            'equipment_compatibility' => $compat,
        ]);

        $part = Part::where('sku', 'COMPAT-1')->first();
        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Equipment compatibility')
            ->assertSee('Toyota Corolla 2014–2018');
    }

    public function test_admin_can_create_product_without_equipment_compatibility(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/products', [
                'name' => 'Generic bolt',
                'sku' => 'NOCOMPAT-1',
                'cost_price' => 1.00,
                'reorder_threshold' => 1,
                'reorder_quantity' => 5,
                'is_active' => true,
            ])
            ->assertRedirect('/admin/products');

        $this->assertDatabaseHas('parts', [
            'sku' => 'NOCOMPAT-1',
            'equipment_compatibility' => null,
        ]);

        $part = Part::where('sku', 'NOCOMPAT-1')->first();
        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Equipment compatibility')
            ->assertSee('—');
    }

    public function test_admin_can_update_product_equipment_compatibility(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'equipment_compatibility' => null,
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/products/{$part->id}", [
                'name' => $part->name,
                'sku' => $part->sku,
                'cost_price' => $part->cost_price,
                'reorder_threshold' => $part->reorder_threshold,
                'reorder_quantity' => $part->reorder_quantity,
                'is_active' => true,
                'equipment_compatibility' => 'Yamaha FZ-16, Honda CB350',
            ])
            ->assertRedirect('/admin/products');

        $part->refresh();
        $this->assertSame('Yamaha FZ-16, Honda CB350', $part->equipment_compatibility);
    }

    public function test_admin_can_clear_equipment_compatibility_on_update(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'equipment_compatibility' => 'Initial compatibility text',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/products/{$part->id}", [
                'name' => $part->name,
                'sku' => $part->sku,
                'cost_price' => $part->cost_price,
                'reorder_threshold' => $part->reorder_threshold,
                'reorder_quantity' => $part->reorder_quantity,
                'is_active' => true,
                'equipment_compatibility' => null,
            ])
            ->assertRedirect('/admin/products');

        $part->refresh();
        $this->assertNull($part->equipment_compatibility);
    }

    public function test_show_page_renders_equipment_compatibility_card(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Brake pad set',
            'equipment_compatibility' => 'Maruti Swift, Hyundai i20',
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Equipment compatibility')
            ->assertSee('Maruti Swift, Hyundai i20');
    }

    public function test_show_page_renders_dash_when_equipment_compatibility_empty(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Universal fitting',
            'equipment_compatibility' => null,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/products/{$part->id}")
            ->assertOk()
            ->assertSee('Equipment compatibility')
            ->assertSee('—');
    }

    public function test_index_page_does_not_render_equipment_compatibility(): void
    {
        $uniqueCompat = 'ZZZ-UNIQUE-COMPAT-'.uniqid();
        Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Product with hidden compatibility',
            'equipment_compatibility' => $uniqueCompat,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/products')
            ->assertOk()
            ->assertSee('Product with hidden compatibility')
            ->assertDontSee($uniqueCompat);
    }
}
