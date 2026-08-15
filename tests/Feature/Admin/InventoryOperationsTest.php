<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementType;
use App\Models\Batch;
use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\SerialNumber;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Workshop;
use App\Services\SettingService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Smoke tests for Phase 5 (inventory operations + batches + serials):
 * stock adjustments workflow, inter-bin transfers, batch & serial-number
 * uniqueness, append-only enforcement on stock movements.
 */
class InventoryOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected Workshop $workshop;

    protected Part $part;

    protected BinLocation $sourceBin;

    protected BinLocation $destBin;

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

        $this->part = Part::factory()->create(['workshop_id' => $this->workshop->id]);
        $this->sourceBin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id, 'code' => 'SRC']);
        $this->destBin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id, 'code' => 'DST']);
    }

    // ---------------------------------------------------------------
    // Stock adjustments
    // ---------------------------------------------------------------

    public function test_admin_can_create_stock_adjustment(): void
    {
        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'quantity' => 10,
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/stock-adjustments', [
                'reason' => 'cycle_count',
                'notes' => 'routine count',
                'items' => [
                    ['inventory_item_id' => $item->id, 'adjustment_quantity' => -2, 'unit_cost' => 5],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('stock_adjustments', [
            'workshop_id' => $this->workshop->id,
            'reason' => 'cycle_count',
            'requested_by' => $this->admin->id,
        ]);

        // Negative stock protection prevents -2 with default setting; verify error path separately.
    }

    public function test_adjustment_with_positive_delta_applies_immediately_when_approval_disabled(): void
    {
        Cache::flush();
        app(SettingService::class)->set(
            'inventory.require_adjustment_approval',
            false,
            $this->workshop->id,
            'inventory',
            'bool',
        );

        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'quantity' => 10,
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/stock-adjustments', [
            'reason' => 'found',
            'items' => [
                ['inventory_item_id' => $item->id, 'adjustment_quantity' => 3, 'unit_cost' => 5],
            ],
        ]);
        $response->assertRedirect();

        $adj = StockAdjustment::query()->latest('id')->first();
        $this->assertSame(StockAdjustment::STATUS_APPLIED, $adj->status);
        $this->assertSame(13.0, (float) $item->fresh()->quantity);

        // Ledger has one adjustment row.
        $this->assertSame(1, $adj->items()->first()->inventoryItem->stockMovements()->count());
    }

    public function test_adjustment_blocks_below_zero_by_default(): void
    {
        Cache::flush();
        // Disable approval so apply runs immediately and the negative-stock
        // gate inside StockMovementService triggers.
        app(SettingService::class)->set(
            'inventory.require_adjustment_approval',
            false,
            $this->workshop->id,
            'inventory',
            'bool',
        );

        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/stock-adjustments', [
                'reason' => 'shrinkage',
                'items' => [
                    ['inventory_item_id' => $item->id, 'adjustment_quantity' => -10],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('adjustment');

        $this->assertSame(2.0, (float) $item->fresh()->quantity);
    }

    public function test_adjustment_allows_negative_when_setting_enabled(): void
    {
        Cache::flush();
        // Disable approval so the adjustment applies immediately and the
        // allow_negative_stock gate is exercised at movement time.
        app(SettingService::class)->set(
            'inventory.require_adjustment_approval',
            false,
            $this->workshop->id,
            'inventory',
            'bool',
        );
        app(SettingService::class)->set(
            'inventory.allow_negative_stock',
            true,
            $this->workshop->id,
            'inventory',
            'bool',
        );

        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/stock-adjustments', [
                'reason' => 'shrinkage',
                'items' => [
                    ['inventory_item_id' => $item->id, 'adjustment_quantity' => -10],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(-8.0, (float) $item->fresh()->quantity);
    }

    public function test_requester_cannot_self_approve(): void
    {
        Cache::flush();
        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'quantity' => 10,
        ]);

        $adj = StockAdjustment::create([
            'workshop_id' => $this->workshop->id,
            'adjustment_number' => 'ADJ-2026-0001',
            'status' => StockAdjustment::STATUS_PENDING,
            'reason' => 'cycle_count',
            'requested_by' => $this->admin->id,
        ]);
        $adj->items()->create([
            'inventory_item_id' => $item->id,
            'before_quantity' => 10,
            'adjustment_quantity' => -2,
            'after_quantity' => 8,
            'unit_cost' => 5,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/stock-adjustments/{$adj->id}/approve")
            ->assertRedirect()
            ->assertSessionHasErrors('adjustment');

        $this->assertSame(StockAdjustment::STATUS_PENDING, $adj->fresh()->status);
    }

    public function test_other_admin_can_approve_pending_adjustment(): void
    {
        Cache::flush();
        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'quantity' => 10,
        ]);

        $otherAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);

        $adj = StockAdjustment::create([
            'workshop_id' => $this->workshop->id,
            'adjustment_number' => 'ADJ-2026-0002',
            'status' => StockAdjustment::STATUS_PENDING,
            'reason' => 'cycle_count',
            'requested_by' => $this->staff->id,
        ]);
        $adj->items()->create([
            'inventory_item_id' => $item->id,
            'before_quantity' => 10,
            'adjustment_quantity' => 2,
            'after_quantity' => 12,
            'unit_cost' => 5,
        ]);

        $this->actingAs($otherAdmin)
            ->post("/admin/stock-adjustments/{$adj->id}/approve")
            ->assertRedirect();

        $fresh = $adj->fresh();
        $this->assertSame(StockAdjustment::STATUS_APPLIED, $fresh->status);
        $this->assertSame(12.0, (float) $item->fresh()->quantity);
    }

    public function test_rejected_adjustment_leaves_quantity_unchanged(): void
    {
        Cache::flush();
        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'quantity' => 10,
        ]);

        $adj = StockAdjustment::create([
            'workshop_id' => $this->workshop->id,
            'adjustment_number' => 'ADJ-2026-0003',
            'status' => StockAdjustment::STATUS_PENDING,
            'reason' => 'cycle_count',
            'requested_by' => $this->staff->id,
        ]);
        $adj->items()->create([
            'inventory_item_id' => $item->id,
            'before_quantity' => 10,
            'adjustment_quantity' => -2,
            'after_quantity' => 8,
            'unit_cost' => 5,
        ]);

        $this->actingAs($this->admin)
            ->post("/admin/stock-adjustments/{$adj->id}/reject", ['reason' => 'wrong'])
            ->assertRedirect();

        $this->assertSame(StockAdjustment::STATUS_REJECTED, $adj->fresh()->status);
        $this->assertSame(10.0, (float) $item->fresh()->quantity);
    }

    // ---------------------------------------------------------------
    // Stock transfers
    // ---------------------------------------------------------------

    public function test_transfer_atomic_decrement_and_increment(): void
    {
        $item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'batch_number' => null,
            'quantity' => 10,
            'cost_price' => 5,
        ]);

        $response = $this->actingAs($this->admin)->post('/admin/stock-transfers', [
            'source_bin_id' => $this->sourceBin->id,
            'destination_bin_id' => $this->destBin->id,
            'items' => [
                ['part_id' => $this->part->id, 'quantity' => 4],
            ],
        ]);
        $response->assertRedirect();

        $t = StockTransfer::query()->latest('id')->first();

        $this->actingAs($this->admin)
            ->post("/admin/stock-transfers/{$t->id}/dispatch")
            ->assertRedirect();

        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $t->fresh()->status);
        $this->assertSame(6.0, (float) $item->fresh()->quantity);

        $this->actingAs($this->admin)
            ->post("/admin/stock-transfers/{$t->id}/receive")
            ->assertRedirect();

        $this->assertSame(StockTransfer::STATUS_RECEIVED, $t->fresh()->status);

        $destBucket = InventoryItem::query()
            ->where('workshop_id', $this->workshop->id)
            ->where('part_id', $this->part->id)
            ->where('bin_id', $this->destBin->id)
            ->first();
        $this->assertNotNull($destBucket);
        $this->assertSame(4.0, (float) $destBucket->quantity);

        // Stock movements: 1 transfer_out + 1 transfer_in
        $this->assertSame(1, StockMovement::query()
            ->where('type', StockMovementType::TransferOut)
            ->where('reference_id', $t->id)
            ->count());
        $this->assertSame(1, StockMovement::query()
            ->where('type', StockMovementType::TransferIn)
            ->where('reference_id', $t->id)
            ->count());
    }

    public function test_transfer_rejects_same_source_and_destination(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/stock-transfers', [
                'source_bin_id' => $this->sourceBin->id,
                'destination_bin_id' => $this->sourceBin->id,
                'items' => [
                    ['part_id' => $this->part->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('transfer');
    }

    public function test_transfer_blocks_when_source_insufficient(): void
    {
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'bin_id' => $this->sourceBin->id,
            'batch_number' => null,
            'quantity' => 2,
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/stock-transfers', [
                'source_bin_id' => $this->sourceBin->id,
                'destination_bin_id' => $this->destBin->id,
                'items' => [
                    ['part_id' => $this->part->id, 'quantity' => 99],
                ],
            ])
            ->assertRedirect();

        $t = StockTransfer::query()->latest('id')->first();

        $this->actingAs($this->admin)
            ->post("/admin/stock-transfers/{$t->id}/dispatch")
            ->assertRedirect()
            ->assertSessionHasErrors('transfer');
    }

    // ---------------------------------------------------------------
    // Batches + serials
    // ---------------------------------------------------------------

    public function test_batch_uniqueness_per_workshop_and_part(): void
    {
        Batch::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'batch_number' => 'B-001',
        ]);

        $this->expectException(QueryException::class);
        Batch::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'batch_number' => 'B-001',
        ]);
    }

    public function test_serial_number_uniqueness_per_part(): void
    {
        SerialNumber::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'serial' => 'SN-XYZ',
        ]);

        $this->expectException(QueryException::class);
        SerialNumber::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'serial' => 'SN-XYZ',
        ]);
    }

    public function test_batch_expiring_soon_scope(): void
    {
        $this->actingAs($this->admin);

        $soon = Batch::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'expires_at' => now()->addDays(5),
        ]);
        $far = Batch::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'expires_at' => now()->addDays(60),
        ]);

        $this->assertSame(1, Batch::query()->expiringSoon(30)->where('id', $soon->id)->count());
        $this->assertSame(0, Batch::query()->expiringSoon(30)->where('id', $far->id)->count());
    }

    // ---------------------------------------------------------------
    // Append-only enforcement
    // ---------------------------------------------------------------

    public function test_stock_movement_cannot_be_updated(): void
    {
        $movement = StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'type' => StockMovementType::ManualAdjustment,
            'quantity' => -1,
            'reason' => 'test',
            'occurred_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $movement->update(['quantity' => 999]);
    }

    public function test_stock_movement_cannot_be_deleted(): void
    {
        $movement = StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'type' => StockMovementType::ManualAdjustment,
            'quantity' => -1,
            'reason' => 'test',
            'occurred_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $movement->delete();
    }
}
