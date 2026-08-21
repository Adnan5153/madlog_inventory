<?php

namespace Tests\Feature\Admin;

use App\Enums\BatteryChemistry;
use App\Models\Battery;
use App\Models\BatteryInventoryItem;
use App\Models\BatteryStockAdjustment;
use App\Models\BatteryStockAdjustmentItem;
use App\Models\BatteryStockMovement;
use App\Models\BinLocation;
use App\Models\Permission;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the Battery Management Module: master-data CRUD,
 * stock-adjustment workflow, search/filter, audit log, and cross-tenant
 * isolation.
 */
class BatteryModuleTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'battery_code' => 'BTY-0001',
            'sku' => 'BAT-0001',
            'name' => 'Battery 12V 60Ah',
            'barcode' => '1111111111111',
            'brand' => 'Bosch',
            'manufacturer_part_number' => 'MPN-ABC123',
            'description' => 'Premium automotive battery',
            'battery_type' => BatteryChemistry::LeadAcid->value,
            'application_type' => 'automotive',
            'condition' => 'new',
            'status' => 'active',
            'voltage' => 12.00,
            'capacity_ah' => 60.00,
            'cold_cranking_amps' => 540,
            'reserve_capacity' => 100,
            'terminal_type' => 'top',
            'length_mm' => 240.00,
            'width_mm' => 175.00,
            'height_mm' => 190.00,
            'weight_kg' => 16.500,
            'polarity' => 'positive',
            'cost_price' => 75.00,
            'reorder_threshold' => 5,
            'reorder_quantity' => 20,
            'warranty_period_months' => 24,
            'is_active' => true,
        ], $overrides);
    }

    public function test_index_renders_for_authorized_user(): void
    {
        Battery::factory()->count(3)->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)->get('/admin/batteries');

        $response->assertOk();
        $response->assertSee('Batteries');
    }

    public function test_index_renders_for_staff_with_view_permission(): void
    {
        $perm = Permission::firstOrCreate(
            ['name' => 'batteries.view'],
            ['group' => 'batteries', 'description' => 'View batteries'],
        );
        $this->staff->givePermissionTo($perm);

        $this->actingAs($this->staff)
            ->get('/admin/batteries')
            ->assertOk();
    }

    public function test_index_renders_for_staff_workshop_member(): void
    {
        // Workshop-scoped staff always have read access to workshop
        // resources by default (the viewAny policy only requires an
        // authenticated staff user). Confirm the standard baseline.
        $this->actingAs($this->staff)
            ->get('/admin/batteries')
            ->assertOk();
    }

    public function test_admin_can_create_battery_and_writes_audit_log(): void
    {
        $supplier = Supplier::factory()->create(['workshop_id' => $this->workshop->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->post('/admin/batteries', $this->validPayload([
                'supplier_id' => $supplier->id,
                'bin_location_id' => $bin->id,
            ]))
            ->assertRedirect('/admin/batteries');

        $this->assertDatabaseHas('batteries', [
            'workshop_id' => $this->workshop->id,
            'battery_code' => 'BTY-0001',
            'sku' => 'BAT-0001',
            'name' => 'Battery 12V 60Ah',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'battery.created',
        ]);
    }

    public function test_create_rejects_missing_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/batteries', ['name' => 'Incomplete'])
            ->assertSessionHasErrors([
                'battery_code',
                'battery_type',
                'voltage',
                'capacity_ah',
                'cost_price',
                'condition',
                'status',
                'reorder_threshold',
                'reorder_quantity',
                'is_active',
            ]);
    }

    public function test_create_rejects_duplicate_sku_within_workshop(): void
    {
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'DUP-SKU',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/batteries', $this->validPayload(['sku' => 'DUP-SKU']))
            ->assertSessionHasErrors('sku');
    }

    public function test_create_rejects_duplicate_battery_code_within_workshop(): void
    {
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_code' => 'BTY-DUP',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/batteries', $this->validPayload(['battery_code' => 'BTY-DUP', 'sku' => 'OTHER-SKU']))
            ->assertSessionHasErrors('battery_code');
    }

    public function test_create_rejects_duplicate_barcode_within_workshop(): void
    {
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'barcode' => '9999999999999',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/batteries', $this->validPayload([
                'barcode' => '9999999999999',
                'battery_code' => 'BTY-NEW',
                'sku' => 'NEW-SKU',
            ]))
            ->assertSessionHasErrors('barcode');
    }

    public function test_admin_can_update_battery_and_audit_records_diff(): void
    {
        $battery = Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Old name',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/batteries/{$battery->id}", $this->validPayload([
                'name' => 'New name',
                'battery_code' => $battery->battery_code,
                'sku' => $battery->sku,
            ]))
            ->assertRedirect('/admin/batteries');

        $this->assertDatabaseHas('batteries', [
            'id' => $battery->id,
            'name' => 'New name',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'battery.updated',
            'subject_type' => Battery::class,
            'subject_id' => $battery->id,
        ]);
    }

    public function test_delete_blocked_when_inventory_items_exist(): void
    {
        $battery = Battery::factory()->create(['workshop_id' => $this->workshop->id]);
        BatteryInventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_id' => $battery->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/batteries/{$battery->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('battery');

        $this->assertDatabaseHas('batteries', ['id' => $battery->id]);
    }

    public function test_delete_blocked_when_stock_movements_exist(): void
    {
        $battery = Battery::factory()->create(['workshop_id' => $this->workshop->id]);
        BatteryStockMovement::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_id' => $battery->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/batteries/{$battery->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('battery');
    }

    public function test_delete_succeeds_when_no_history(): void
    {
        $battery = Battery::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/batteries/{$battery->id}")
            ->assertRedirect('/admin/batteries');

        $this->assertSoftDeleted('batteries', ['id' => $battery->id]);
    }

    public function test_search_finds_by_sku(): void
    {
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'SEARCH-123',
            'name' => 'Battery A',
        ]);
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'OTHER-999',
            'name' => 'Battery B',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/batteries-search?q=SEARCH-123');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
        $response->assertJsonFragment(['word' => 'battery']);
    }

    public function test_search_finds_by_battery_code(): void
    {
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_code' => 'BTY-001',
        ]);
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_code' => 'BTY-002',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/batteries-search?q=BTY-001');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
    }

    public function test_filter_by_battery_type(): void
    {
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_type' => BatteryChemistry::LeadAcid->value,
            'name' => 'Lead',
        ]);
        Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_type' => BatteryChemistry::LithiumIronPhosphate->value,
            'name' => 'LFP',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/batteries-search?battery_type=lithium_iron_phosphate');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
    }

    public function test_pagination_preserves_filters(): void
    {
        Battery::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'battery_type' => 'agm',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/batteries-search?battery_type=agm&page=2');

        $response->assertOk();
        $response->assertJsonFragment(['page' => 2]);
    }

    public function test_cross_tenant_rejects_other_workshop_battery(): void
    {
        $batteryInOther = Battery::factory()->create([
            'workshop_id' => $this->otherWorkshop->id,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/batteries/{$batteryInOther->id}")
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->put("/admin/batteries/{$batteryInOther->id}", $this->validPayload())
            ->assertNotFound();
    }

    public function test_stock_adjustment_approve_writes_movement_and_updates_inventory(): void
    {
        $battery = Battery::factory()->create(['workshop_id' => $this->workshop->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);
        $inventory = BatteryInventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_id' => $battery->id,
            'bin_id' => $bin->id,
            'quantity' => 10,
        ]);

        $adjustment = BatteryStockAdjustment::factory()->create([
            'workshop_id' => $this->workshop->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
            'reference' => 'BSA-TEST-001',
        ]);
        BatteryStockAdjustmentItem::factory()->create([
            'battery_stock_adjustment_id' => $adjustment->id,
            'battery_id' => $battery->id,
            'bin_id' => $bin->id,
            'battery_inventory_item_id' => $inventory->id,
            'counted_quantity' => 8,
            'quantity' => -2,
            'unit_cost' => 75.00,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/battery-stock-adjustments/{$adjustment->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseHas('battery_stock_adjustments', [
            'id' => $adjustment->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('battery_stock_movements', [
            'workshop_id' => $this->workshop->id,
            'battery_id' => $battery->id,
            'type' => 'manual_adjustment',
            'reference_type' => BatteryStockAdjustment::class,
            'reference_id' => $adjustment->id,
        ]);

        $this->assertEquals(8.0, (float) $inventory->fresh()->quantity);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'battery-stock-adjustment.approved',
        ]);
    }

    public function test_stock_adjustment_reject_does_not_write_movement(): void
    {
        $adjustment = BatteryStockAdjustment::factory()->create([
            'workshop_id' => $this->workshop->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/battery-stock-adjustments/{$adjustment->id}/reject")
            ->assertRedirect();

        $this->assertDatabaseHas('battery_stock_adjustments', [
            'id' => $adjustment->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseCount('battery_stock_movements', 0);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'battery-stock-adjustment.rejected',
        ]);
    }
}
