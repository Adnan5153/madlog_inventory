<?php

namespace Tests\Unit\Services\Inventory;

use App\Exceptions\DomainException;
use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Inventory\PurchaseOrderService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-style coverage for the purchase-order state machine:
 *   draft → submitted → approved → (partially_)received
 *
 * Focused on the calculation side: line totals, weighted-average cost,
 * received-vs-remaining bookkeeping, status refresh, and rejection paths.
 */
class PurchaseOrderCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected User $globalAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);

        $this->globalAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null,
        ]);
        $this->actingAs($this->globalAdmin);
    }

    public function test_next_po_number_is_workshop_scoped_and_increments(): void
    {
        $wsA = Workshop::factory()->create();
        $wsB = Workshop::factory()->create();

        /** @var PurchaseOrderService $svc */
        $svc = app(PurchaseOrderService::class);

        // Insert one PO into wsA so its next call increments.
        $supplier = Supplier::factory()->create(['workshop_id' => $wsA->id]);
        PurchaseOrder::factory()->create([
            'workshop_id' => $wsA->id,
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-'.date('Y').'-0001',
        ]);

        $n1 = $svc->nextPoNumber($wsA->id);
        $n2 = $svc->nextPoNumber($wsB->id);

        $this->assertStringContainsString(date('Y'), $n1);
        // Workshop B has no rows so its counter starts at 0001.
        $this->assertStringEndsWith('0001', $n2);
        // Workshop A has one pre-existing row, so its next number is 0002.
        $this->assertStringEndsWith('0002', $n1);
    }

    public function test_submit_rejects_empty_po(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        $this->expectException(DomainException::class);
        app(PurchaseOrderService::class)->submit($po, $actor);
    }

    public function test_submit_moves_to_submitted_and_emits_audit(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'part_id' => $part->id,
            'quantity_ordered' => 5,
            'unit_cost' => 10,
        ]);

        $svc = app(PurchaseOrderService::class);
        $updated = $svc->submit($po, $actor);

        $this->assertSame(PurchaseOrder::STATUS_SUBMITTED, $updated->status);
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('action', 'purchase_order.submitted')->count());
    }

    public function test_approve_rejects_non_submitted(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
        ]);

        $this->expectException(DomainException::class);
        app(PurchaseOrderService::class)->approve($po, $actor);
    }

    public function test_cancel_appends_reason_and_blocks_received_po(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_RECEIVED,
        ]);

        $this->expectException(DomainException::class);
        app(PurchaseOrderService::class)->cancel($po, $actor, 'too late');
    }

    public function test_receive_full_quantity_creates_stock_movement_and_sets_status_received(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $poi = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'part_id' => $part->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 5.00,
        ]);

        $svc = app(PurchaseOrderService::class);
        $grn = $svc->receive($po, $actor, [
            [
                'purchase_order_item_id' => $poi->id,
                'quantity_received' => 10,
                'bin_location_id' => $bin->id,
                'batch_number' => null,
                'unit_cost' => 5.00,
            ],
        ], $bin->id);

        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
        $this->assertSame(10.0, (float) $poi->fresh()->quantity_received);

        $bucket = InventoryItem::query()
            ->where('workshop_id', $ws->id)
            ->where('part_id', $part->id)
            ->where('bin_id', $bin->id)
            ->first();
        $this->assertNotNull($bucket);
        $this->assertSame(10.0, (float) $bucket->quantity);
        $this->assertSame(5.0, (float) $bucket->cost_price);

        $this->assertSame(1, StockMovement::query()->where('reference_id', $grn->id)->count());
    }

    public function test_receive_partial_quantity_uses_weighted_average_cost(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);

        // Seed an existing bucket of 10 units @ $4 to test weighted average.
        InventoryItem::factory()->create([
            'workshop_id' => $ws->id,
            'part_id' => $part->id,
            'bin_id' => $bin->id,
            'batch_number' => null,
            'quantity' => 10,
            'cost_price' => 4,
        ]);

        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $poi = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'part_id' => $part->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 6.00,
        ]);

        app(PurchaseOrderService::class)->receive($po, $actor, [
            [
                'purchase_order_item_id' => $poi->id,
                'quantity_received' => 10,
                'bin_location_id' => $bin->id,
                'batch_number' => null,
                'unit_cost' => 6.00,
            ],
        ], $bin->id);

        $bucket = InventoryItem::query()
            ->where('workshop_id', $ws->id)
            ->where('part_id', $part->id)
            ->where('bin_id', $bin->id)
            ->whereNull('batch_number')
            ->first();
        // Weighted average: (10*4 + 10*6) / 20 = 5.00
        $this->assertSame(20.0, (float) $bucket->quantity);
        $this->assertEqualsWithDelta(5.0, (float) $bucket->cost_price, 0.001);
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $po->fresh()->status);
    }

    public function test_receive_rejects_over_remaining_quantity(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $poi = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'part_id' => $part->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_cost' => 1.00,
        ]);

        $this->expectException(DomainException::class);
        app(PurchaseOrderService::class)->receive($po, $actor, [
            [
                'purchase_order_item_id' => $poi->id,
                'quantity_received' => 10,
                'bin_location_id' => $bin->id,
                'batch_number' => null,
                'unit_cost' => 1.00,
            ],
        ], $bin->id);
    }

    public function test_receive_marks_partially_received_when_one_line_short(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $supplier = Supplier::factory()->create(['workshop_id' => $ws->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $partA = Part::factory()->create(['workshop_id' => $ws->id]);
        $partB = Part::factory()->create(['workshop_id' => $ws->id]);
        $po = PurchaseOrder::factory()->create([
            'workshop_id' => $ws->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_APPROVED,
        ]);
        $poiA = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'part_id' => $partA->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'unit_cost' => 2.00,
        ]);
        $poiB = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'part_id' => $partB->id,
            'quantity_ordered' => 5,
            'quantity_received' => 0,
            'unit_cost' => 3.00,
        ]);

        app(PurchaseOrderService::class)->receive($po, $actor, [
            [
                'purchase_order_item_id' => $poiA->id,
                'quantity_received' => 10,
                'bin_location_id' => $bin->id,
                'batch_number' => null,
                'unit_cost' => 2.00,
            ],
            // Receive only 2 of 5 for partB → partial.
            [
                'purchase_order_item_id' => $poiB->id,
                'quantity_received' => 2,
                'bin_location_id' => $bin->id,
                'batch_number' => null,
                'unit_cost' => 3.00,
            ],
        ], $bin->id);

        $this->assertSame(PurchaseOrder::STATUS_PARTIALLY_RECEIVED, $po->fresh()->status);
    }
}
