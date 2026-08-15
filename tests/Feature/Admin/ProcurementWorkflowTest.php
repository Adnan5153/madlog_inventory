<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\BinLocation;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Inventory\PurchaseOrderService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for Phase 4 (procurement lifecycle):
 * PO draft → submit → approve → receive → GRN, with stock movement
 * ledger entries and inventory bucket updates.
 */
class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected Workshop $workshop;

    protected Supplier $supplier;

    protected Part $part;

    protected BinLocation $bin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->workshop = Workshop::factory()->create();
        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);
        $this->staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->supplier = Supplier::factory()->create(['workshop_id' => $this->workshop->id]);
        $this->part = Part::factory()->create(['workshop_id' => $this->workshop->id]);
        $this->bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id, 'code' => 'A-01']);
    }

    public function test_admin_can_create_purchase_order(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/purchase-orders', [
            'supplier_id' => $this->supplier->id,
            'order_date' => '2026-08-01',
            'expected_date' => '2026-08-15',
            'items' => [
                ['part_id' => $this->part->id, 'quantity_ordered' => 10, 'unit_cost' => 5.00],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('purchase_orders', [
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
            'subtotal' => 50.00,
            'total' => 50.00,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'part_id' => $this->part->id,
            'quantity_ordered' => 10,
            'unit_cost' => 5.00,
            'line_total' => 50.00,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase_order.created',
        ]);
    }

    public function test_submit_requires_lines(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/submit")
            ->assertRedirect()
            ->assertSessionHasErrors('po');
    }

    public function test_submit_advances_to_submitted(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        $po->items()->create([
            'part_id' => $this->part->id,
            'quantity_ordered' => 10,
            'unit_cost' => 5.00,
            'line_total' => 50.00,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/submit")
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $po->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase_order.submitted',
            'subject_id' => $po->id,
        ]);
    }

    public function test_submit_cannot_run_on_approved_po(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/submit")
            ->assertRedirect()
            ->assertSessionHasErrors('po');
    }

    public function test_approve_records_approver_and_advances_status(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_SUBMITTED,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/approve")
            ->assertRedirect();

        $fresh = $po->fresh();
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $fresh->status);
        $this->assertSame($this->admin->id, $fresh->approved_by);
    }

    public function test_staff_cannot_approve(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_SUBMITTED,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/purchase-orders/{$po->id}/approve")
            ->assertStatus(403);
    }

    public function test_cancel_works_on_draft_or_submitted_or_approved(): void
    {
        foreach ([PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SUBMITTED, PurchaseOrder::STATUS_APPROVED] as $status) {
            $po = PurchaseOrder::factory()->create([
                'workshop_id' => $this->workshop->id,
                'supplier_id' => $this->supplier->id,
                'created_by' => $this->admin->id,
                'status' => $status,
            ]);

            $this->actingAs($this->admin)
                ->post("/admin/purchase-orders/{$po->id}/cancel", ['reason' => 'no budget'])
                ->assertRedirect();

            $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $po->fresh()->status);
        }
    }

    public function test_cancel_blocked_when_fully_received(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'received_date' => now(),
        ]);

        // Cancel is a delete-ability; the policy already rejects fully-received
        // POs with 403. We assert the service throws if we somehow get past.
        $this->expectException(DomainException::class);
        app(PurchaseOrderService::class)
            ->cancel($po, $this->admin, 'test');
    }

    public function test_full_receipt_creates_grn_inventory_and_movement(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
        ]);
        $item = $po->items()->create([
            'part_id' => $this->part->id,
            'quantity_ordered' => 10,
            'unit_cost' => 5.00,
            'line_total' => 50.00,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/receive", [
                'bin_location_id' => $this->bin->id,
                'supplier_invoice_number' => 'INV-001',
                'items' => [
                    [
                        'purchase_order_item_id' => $item->id,
                        'quantity_received' => 10,
                        'bin_location_id' => $this->bin->id,
                        'unit_cost' => 5.00,
                    ],
                ],
            ])
            ->assertRedirect();

        // Goods receipt row was created
        $this->assertDatabaseHas('goods_receipts', [
            'purchase_order_id' => $po->id,
            'status' => 'received',
        ]);

        // InventoryItem bucket was created with the received quantity
        $bucket = InventoryItem::query()
            ->where('workshop_id', $this->workshop->id)
            ->where('part_id', $this->part->id)
            ->where('bin_id', $this->bin->id)
            ->first();
        $this->assertNotNull($bucket);
        $this->assertEquals(10.0, (float) $bucket->quantity);

        // StockMovement ledger has a single receipt row tied to this PO via the GRN reference
        $grn = GoodsReceipt::query()->where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($grn);
        $movementCount = StockMovement::query()
            ->where('reference_type', GoodsReceipt::class)
            ->where('reference_id', $grn->id)
            ->count();
        $this->assertSame(1, $movementCount);

        // Purchase order status advanced to fully received
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);

        // Audit log captured the receive event
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'purchase_order.received',
            'subject_id' => $po->id,
        ]);
    }

    public function test_partial_receipt_keeps_po_in_partially_received_status(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
        ]);
        $item = $po->items()->create([
            'part_id' => $this->part->id,
            'quantity_ordered' => 10,
            'unit_cost' => 5.00,
            'line_total' => 50.00,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/receive", [
                'bin_location_id' => $this->bin->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $item->id,
                        'quantity_received' => 4,
                        'bin_location_id' => $this->bin->id,
                        'unit_cost' => 5.00,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->status);
        $this->assertSame(4.0, (float) $item->fresh()->quantity_received);

        // Receive the rest
        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/receive", [
                'bin_location_id' => $this->bin->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $item->id,
                        'quantity_received' => 6,
                        'bin_location_id' => $this->bin->id,
                        'unit_cost' => 5.00,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
        $this->assertSame(10.0, (float) $item->fresh()->quantity_received);
    }

    public function test_receipt_rejects_over_received_quantity(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
        ]);
        $item = $po->items()->create([
            'part_id' => $this->part->id,
            'quantity_ordered' => 10,
            'unit_cost' => 5.00,
            'line_total' => 50.00,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/receive", [
                'bin_location_id' => $this->bin->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $item->id,
                        'quantity_received' => 999,
                        'bin_location_id' => $this->bin->id,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('po');

        $this->assertSame(0.0, (float) $item->fresh()->quantity_received);
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $po->fresh()->status);
    }

    public function test_receipt_blocked_on_draft_po(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        // Receiving a draft PO is blocked at the service layer because
        // the PO isn't in an APPROVED/PARTIALLY_RECEIVED status. The
        // controller returns a redirect with an error in the session.
        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/receive", [
                'items' => [
                    [
                        'purchase_order_item_id' => 1,
                        'quantity_received' => 1,
                        'bin_location_id' => $this->bin->id,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('po');

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $po->fresh()->status);
    }

    public function test_admin_can_view_goods_receipts_list(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/goods-receipts')
            ->assertOk()
            ->assertSee('Goods receipts');
    }

    public function test_stock_movement_receipt_type_recorded(): void
    {
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $this->workshop->id,
            'supplier_id' => $this->supplier->id,
            'created_by' => $this->admin->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => $this->admin->id,
        ]);
        $item = $po->items()->create([
            'part_id' => $this->part->id,
            'quantity_ordered' => 10,
            'unit_cost' => 5.00,
            'line_total' => 50.00,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/purchase-orders/{$po->id}/receive", [
                'bin_location_id' => $this->bin->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $item->id,
                        'quantity_received' => 10,
                        'bin_location_id' => $this->bin->id,
                        'unit_cost' => 5.00,
                    ],
                ],
            ])
            ->assertRedirect();

        $grn = GoodsReceipt::query()->where('purchase_order_id', $po->id)->first();
        $movement = StockMovement::query()
            ->where('reference_type', GoodsReceipt::class)
            ->where('reference_id', $grn->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(StockMovementType::Receipt, $movement->type);
        $this->assertSame(10.0, (float) $movement->quantity);
        $this->assertSame($this->bin->id, (int) $movement->bin_id);
    }
}
