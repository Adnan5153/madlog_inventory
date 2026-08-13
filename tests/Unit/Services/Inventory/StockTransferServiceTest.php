<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Inventory\StockTransferService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit-style coverage for the inter-bin stock transfer lifecycle.
 *
 * Tests run as a global admin (workshop_id = null) so the WorkshopScope
 * global scope lets every workshop-scoped read return rows. The actor
 * user is a separate workshop-scoped admin created per-test.
 */
class StockTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $globalAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);

        // Acting as a global admin (role=admin, workshop_id=null) so the
        // global scope returns every workshop row during the service's
        // internal lookups.
        $this->globalAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null,
        ]);
        $this->actingAs($this->globalAdmin);
    }

    public function test_create_persists_transfer_with_draft_status(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $source = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $dest = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);

        $svc = app(StockTransferService::class);
        $t = $svc->create($actor, $ws->id, $source->id, $dest->id, 'notes', [
            ['part_id' => $part->id, 'quantity' => 4],
        ]);

        $this->assertSame(StockTransfer::STATUS_DRAFT, $t->status);
        $this->assertNotEmpty($t->transfer_number);
        $this->assertSame(1, $t->items()->count());
        $this->assertSame((float) 4, (float) $t->items()->first()->quantity);
        // Audit observer fires `stock_transfer.created` automatically; the
        // service also records an explicit row. Either way the action
        // appears at least once.
        $this->assertGreaterThanOrEqual(1, AuditLog::query()->where('action', 'stock_transfer.created')->count());
    }

    public function test_create_rejects_same_source_and_destination(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);

        $this->expectException(DomainException::class);
        app(StockTransferService::class)->create($actor, $ws->id, $bin->id, $bin->id, null, [
            ['part_id' => $part->id, 'quantity' => 1],
        ]);
    }

    public function test_create_rejects_empty_items(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $source = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $dest = BinLocation::factory()->create(['workshop_id' => $ws->id]);

        $this->expectException(DomainException::class);
        app(StockTransferService::class)->create($actor, $ws->id, $source->id, $dest->id, null, []);
    }

    public function test_dispatch_decrements_source_and_emits_transfer_out(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $source = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $dest = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);
        $bucket = InventoryItem::factory()->create([
            'workshop_id' => $ws->id,
            'part_id' => $part->id,
            'bin_id' => $source->id,
            'batch_number' => null,
            'quantity' => 10,
        ]);

        $svc = app(StockTransferService::class);
        $t = $svc->create($actor, $ws->id, $source->id, $dest->id, null, [
            ['part_id' => $part->id, 'quantity' => 3, 'batch_number' => null],
        ]);

        $svc->dispatch($t, $actor);

        $this->assertSame(StockTransfer::STATUS_IN_TRANSIT, $t->fresh()->status);
        $this->assertSame(7.0, (float) $bucket->fresh()->quantity);

        $movement = StockMovement::query()
            ->where('part_id', $part->id)
            ->where('type', StockMovementType::TransferOut)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(-3.0, (float) $movement->quantity);
    }

    public function test_dispatch_blocks_when_source_stock_is_insufficient(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $source = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $dest = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $ws->id,
            'part_id' => $part->id,
            'bin_id' => $source->id,
            'batch_number' => null,
            'quantity' => 1,
        ]);

        $svc = app(StockTransferService::class);
        $t = $svc->create($actor, $ws->id, $source->id, $dest->id, null, [
            ['part_id' => $part->id, 'quantity' => 10, 'batch_number' => null],
        ]);

        $this->expectException(DomainException::class);
        $svc->dispatch($t, $actor);
    }

    public function test_dispatch_refuses_non_draft_transfer(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $source = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $dest = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $ws->id,
            'part_id' => $part->id,
            'bin_id' => $source->id,
            'batch_number' => null,
            'quantity' => 10,
        ]);

        $svc = app(StockTransferService::class);
        $t = $svc->create($actor, $ws->id, $source->id, $dest->id, null, [
            ['part_id' => $part->id, 'quantity' => 2, 'batch_number' => null],
        ]);
        $svc->dispatch($t, $actor);

        $this->expectException(DomainException::class);
        $svc->dispatch($t->fresh(), $actor);
    }

    public function test_receive_increments_destination_and_creates_bucket_if_missing(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $source = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $dest = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $ws->id,
            'part_id' => $part->id,
            'bin_id' => $source->id,
            'batch_number' => null,
            'quantity' => 10,
        ]);

        $svc = app(StockTransferService::class);
        $t = $svc->create($actor, $ws->id, $source->id, $dest->id, null, [
            ['part_id' => $part->id, 'quantity' => 4, 'batch_number' => null],
        ]);
        $svc->dispatch($t, $actor);
        $svc->receive($t->fresh(), $actor);

        $bucket = InventoryItem::query()
            ->where('workshop_id', $ws->id)
            ->where('part_id', $part->id)
            ->where('bin_id', $dest->id)
            ->first();
        $this->assertNotNull($bucket);
        $this->assertSame(4.0, (float) $bucket->quantity);
        $this->assertSame(StockTransfer::STATUS_RECEIVED, $t->fresh()->status);
    }

    public function test_receive_refuses_non_in_transit_transfer(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $source = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $dest = BinLocation::factory()->create(['workshop_id' => $ws->id]);
        $part = Part::factory()->create(['workshop_id' => $ws->id]);

        $svc = app(StockTransferService::class);
        $t = $svc->create($actor, $ws->id, $source->id, $dest->id, null, [
            ['part_id' => $part->id, 'quantity' => 1],
        ]);

        $this->expectException(DomainException::class);
        $svc->receive($t, $actor);
    }
}
