<?php

namespace Tests\Feature\Admin;

use App\Enums\LubricantApplication;
use App\Enums\LubricantType;
use App\Enums\LubricantViscosity;
use App\Models\BinLocation;
use App\Models\Lubricant;
use App\Models\LubricantInventoryItem;
use App\Models\LubricantStockAdjustment;
use App\Models\LubricantStockAdjustmentItem;
use App\Models\LubricantStockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the Lubricants Management Module: master-data CRUD,
 * stock-adjustment workflow, search/filter, audit log, and cross-tenant
 * isolation.
 */
class LubricantModuleTest extends TestCase
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
            'lubricant_code' => 'LUB-0001',
            'sku' => 'LUB-SKU-0001',
            'name' => 'Engine Oil 5W-30 5L',
            'barcode' => '1111111111111',
            'brand' => 'Castrol',
            'manufacturer' => 'BP',
            'manufacturer_part_number' => 'MPN-LUB-001',
            'description' => 'Premium synthetic engine oil',
            'lubricant_type' => LubricantType::FullySynthetic->value,
            'viscosity_grade' => LubricantViscosity::Sae5w30->value,
            'application_type' => LubricantApplication::EngineOil->value,
            'status' => 'active',
            'oem_specification' => 'MB-Approval 229.51',
            'acea_specification' => 'A3/B4',
            'api_specification' => 'SN',
            'package_type' => 'bottle',
            'package_size' => 5.00,
            'package_unit' => 'L',
            'cost_price' => 25.00,
            'reorder_threshold' => 5,
            'reorder_quantity' => 20,
            'is_active' => true,
        ], $overrides);
    }

    public function test_index_renders_for_authorized_user(): void
    {
        Lubricant::factory()->count(3)->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)->get('/admin/lubricants');

        $response->assertOk();
        $response->assertSee('Lubricants');
    }

    public function test_index_renders_for_staff_workshop_member(): void
    {
        // Workshop-scoped staff always have read access to workshop
        // resources by default (the viewAny policy only requires an
        // authenticated staff user). Confirm the standard baseline.
        $this->actingAs($this->staff)
            ->get('/admin/lubricants')
            ->assertOk();
    }

    public function test_admin_can_create_lubricant_and_writes_audit_log(): void
    {
        $supplier = Supplier::factory()->create(['workshop_id' => $this->workshop->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->post('/admin/lubricants', $this->validPayload([
                'supplier_id' => $supplier->id,
                'bin_location_id' => $bin->id,
            ]))
            ->assertRedirect('/admin/lubricants');

        $this->assertDatabaseHas('lubricants', [
            'workshop_id' => $this->workshop->id,
            'lubricant_code' => 'LUB-0001',
            'sku' => 'LUB-SKU-0001',
            'name' => 'Engine Oil 5W-30 5L',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lubricant.created',
        ]);
    }

    public function test_create_rejects_missing_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/lubricants', ['name' => 'Incomplete'])
            ->assertSessionHasErrors([
                'lubricant_code',
                'lubricant_type',
                'package_type',
                'package_size',
                'package_unit',
                'cost_price',
                'status',
                'reorder_threshold',
                'reorder_quantity',
                'is_active',
            ]);
    }

    public function test_create_rejects_duplicate_sku_within_workshop(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'DUP-SKU',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/lubricants', $this->validPayload(['sku' => 'DUP-SKU']))
            ->assertSessionHasErrors('sku');
    }

    public function test_create_rejects_duplicate_lubricant_code_within_workshop(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_code' => 'LUB-DUP',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/lubricants', $this->validPayload(['lubricant_code' => 'LUB-DUP', 'sku' => 'OTHER-SKU']))
            ->assertSessionHasErrors('lubricant_code');
    }

    public function test_create_rejects_duplicate_barcode_within_workshop(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'barcode' => '9999999999999',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/lubricants', $this->validPayload([
                'barcode' => '9999999999999',
                'lubricant_code' => 'LUB-NEW',
                'sku' => 'NEW-SKU',
            ]))
            ->assertSessionHasErrors('barcode');
    }

    public function test_admin_can_update_lubricant_and_audit_records_diff(): void
    {
        $lubricant = Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Old name',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/lubricants/{$lubricant->id}", $this->validPayload([
                'name' => 'New name',
                'lubricant_code' => $lubricant->lubricant_code,
                'sku' => $lubricant->sku,
            ]))
            ->assertRedirect('/admin/lubricants');

        $this->assertDatabaseHas('lubricants', [
            'id' => $lubricant->id,
            'name' => 'New name',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lubricant.updated',
            'subject_type' => Lubricant::class,
            'subject_id' => $lubricant->id,
        ]);
    }

    public function test_delete_blocked_when_inventory_items_exist(): void
    {
        $lubricant = Lubricant::factory()->create(['workshop_id' => $this->workshop->id]);
        LubricantInventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_id' => $lubricant->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/lubricants/{$lubricant->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('lubricant');

        $this->assertDatabaseHas('lubricants', ['id' => $lubricant->id]);
    }

    public function test_delete_blocked_when_stock_movements_exist(): void
    {
        $lubricant = Lubricant::factory()->create(['workshop_id' => $this->workshop->id]);
        LubricantStockMovement::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_id' => $lubricant->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/lubricants/{$lubricant->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('lubricant');
    }

    public function test_delete_succeeds_when_no_history(): void
    {
        $lubricant = Lubricant::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/lubricants/{$lubricant->id}")
            ->assertRedirect('/admin/lubricants');

        $this->assertSoftDeleted('lubricants', ['id' => $lubricant->id]);
    }

    public function test_search_finds_by_sku(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'SEARCH-123',
            'name' => 'Lubricant A',
        ]);
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'sku' => 'OTHER-999',
            'name' => 'Lubricant B',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/lubricants-search?q=SEARCH-123');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
        $response->assertJsonFragment(['word' => 'lubricant']);
    }

    public function test_search_finds_by_lubricant_code(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_code' => 'LUB-001',
        ]);
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_code' => 'LUB-002',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/lubricants-search?q=LUB-001');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
    }

    public function test_filter_by_lubricant_type(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_type' => LubricantType::Mineral->value,
            'name' => 'Mineral',
        ]);
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_type' => LubricantType::FullySynthetic->value,
            'name' => 'Synthetic',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/lubricants-search?lubricant_type=fully_synthetic');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
    }

    public function test_filter_by_application_type(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'application_type' => LubricantApplication::EngineOil->value,
        ]);
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'application_type' => LubricantApplication::Grease->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/lubricants-search?application_type=engine_oil');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
    }

    public function test_filter_by_viscosity_grade(): void
    {
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'viscosity_grade' => LubricantViscosity::Sae5w30->value,
        ]);
        Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
            'viscosity_grade' => LubricantViscosity::IsoVg46->value,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/lubricants-search?viscosity_grade=sae_5w_30');

        $response->assertOk();
        $response->assertJsonFragment(['total' => 1]);
    }

    public function test_pagination_preserves_filters(): void
    {
        Lubricant::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_type' => 'synthetic',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/lubricants-search?lubricant_type=synthetic&page=2');

        $response->assertOk();
        $response->assertJsonFragment(['page' => 2]);
    }

    public function test_cross_tenant_rejects_other_workshop_lubricant(): void
    {
        $lubricantInOther = Lubricant::factory()->create([
            'workshop_id' => $this->otherWorkshop->id,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/lubricants/{$lubricantInOther->id}")
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->put("/admin/lubricants/{$lubricantInOther->id}", $this->validPayload())
            ->assertNotFound();
    }

    public function test_stock_adjustment_approve_writes_movement_and_updates_inventory(): void
    {
        $lubricant = Lubricant::factory()->create(['workshop_id' => $this->workshop->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);
        $inventory = LubricantInventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'lubricant_id' => $lubricant->id,
            'bin_id' => $bin->id,
            'quantity' => 10,
        ]);

        $adjustment = LubricantStockAdjustment::factory()->create([
            'workshop_id' => $this->workshop->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
            'reference' => 'LSA-TEST-001',
        ]);
        LubricantStockAdjustmentItem::factory()->create([
            'lubricant_stock_adjustment_id' => $adjustment->id,
            'lubricant_id' => $lubricant->id,
            'bin_id' => $bin->id,
            'lubricant_inventory_item_id' => $inventory->id,
            'counted_quantity' => 8,
            'quantity' => -2,
            'unit_cost' => 25.00,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/lubricant-stock-adjustments/{$adjustment->id}/approve")
            ->assertRedirect();

        $this->assertDatabaseHas('lubricant_stock_adjustments', [
            'id' => $adjustment->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('lubricant_stock_movements', [
            'workshop_id' => $this->workshop->id,
            'lubricant_id' => $lubricant->id,
            'type' => 'manual_adjustment',
            'reference_type' => LubricantStockAdjustment::class,
            'reference_id' => $adjustment->id,
        ]);

        $this->assertEquals(8.0, (float) $inventory->fresh()->quantity);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lubricant-stock-adjustment.approved',
        ]);
    }

    public function test_stock_adjustment_reject_does_not_write_movement(): void
    {
        $adjustment = LubricantStockAdjustment::factory()->create([
            'workshop_id' => $this->workshop->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/lubricant-stock-adjustments/{$adjustment->id}/reject")
            ->assertRedirect();

        $this->assertDatabaseHas('lubricant_stock_adjustments', [
            'id' => $adjustment->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseCount('lubricant_stock_movements', 0);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'lubricant-stock-adjustment.rejected',
        ]);
    }
}
