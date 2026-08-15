<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Inventory\StockMovementService;
use App\Services\SettingService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Unit-style coverage for the stock movement ledger writer.
 */
class StockMovementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    public function test_record_appends_a_ledger_row_and_updates_quantity(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $item = InventoryItem::factory()->create(['workshop_id' => $ws->id, 'quantity' => 10]);

        $svc = app(StockMovementService::class);
        $movement = $svc->record(StockMovementType::Receipt, $item, 5, $actor, 3.0, 'restock');

        $this->assertSame(15.0, (float) $item->fresh()->quantity);
        $this->assertSame(5.0, (float) $movement->quantity);
        $this->assertSame(StockMovementType::Receipt, $movement->type);
        $this->assertSame('restock', $movement->reason);
    }

    public function test_record_rejects_zero_quantity(): void
    {
        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $item = InventoryItem::factory()->create(['workshop_id' => $ws->id, 'quantity' => 5]);

        $this->expectException(DomainException::class);
        app(StockMovementService::class)->record(StockMovementType::Adjustment, $item, 0, $actor);
    }

    public function test_record_blocks_negative_when_setting_disabled(): void
    {
        Cache::flush();
        // Default is false; explicit reset for safety.
        app(SettingService::class)->set(
            'inventory.allow_negative_stock', false, $this->workshopId(), 'inventory', 'bool'
        );

        $ws = Workshop::factory()->create();
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $item = InventoryItem::factory()->create(['workshop_id' => $ws->id, 'quantity' => 2]);

        $this->expectException(DomainException::class);
        app(StockMovementService::class)->record(StockMovementType::Issue, $item, -10, $actor);
    }

    public function test_record_allows_negative_when_setting_enabled(): void
    {
        Cache::flush();
        $ws = Workshop::factory()->create();
        app(SettingService::class)->set(
            'inventory.allow_negative_stock', true, $ws->id, 'inventory', 'bool'
        );

        $actor = User::factory()->create(['role' => User::ROLE_ADMIN, 'workshop_id' => $ws->id]);
        $item = InventoryItem::factory()->create(['workshop_id' => $ws->id, 'quantity' => 2]);

        $movement = app(StockMovementService::class)->record(StockMovementType::Issue, $item, -5, $actor);

        $this->assertSame(-3.0, (float) $item->fresh()->quantity);
        $this->assertSame(-5.0, (float) $movement->quantity);
    }

    public function test_movement_rows_are_append_only(): void
    {
        $ws = Workshop::factory()->create();
        $item = InventoryItem::factory()->create(['workshop_id' => $ws->id]);

        $movement = StockMovement::create([
            'workshop_id' => $ws->id,
            'part_id' => $item->part_id,
            'bin_id' => $item->bin_id,
            'type' => StockMovementType::ManualAdjustment,
            'quantity' => 1,
            'occurred_at' => now(),
        ]);

        $this->expectException(\LogicException::class);
        $movement->update(['quantity' => 999]);
    }

    protected function workshopId(): int
    {
        // Convenience accessor used in setUp's settings write.
        return Workshop::factory()->create()->id;
    }
}
