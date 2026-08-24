<?php

namespace Tests\Unit\Inventory;

use App\Enums\StockMovementType;
use App\Models\Battery;
use App\Models\BatteryInventoryItem;
use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\StockMovement;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Inventory\ReportService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit coverage for the new aggregation methods on ReportService that
 * back the inventory-intelligence dashboard.
 *
 * Each test seeds a tight scenario (one or two rows of each subsystem)
 * and asserts the shape, ordering, and content of the returned payload.
 */
class ReportServiceAggregationsTest extends TestCase
{
    use RefreshDatabase;

    protected ReportService $service;

    protected Workshop $workshop;

    protected BinLocation $bin;

    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->service = new ReportService;
        $this->workshop = Workshop::factory()->create();
        $this->actor = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);
        $this->bin = BinLocation::factory()->create([
            'workshop_id' => $this->workshop->id,
        ]);
    }

    public function test_monthly_stock_movements_returns_twelve_zero_filled_entries(): void
    {
        $result = $this->service->monthlyStockMovements($this->workshop->id);

        $this->assertCount(12, $result['labels']);
        $this->assertCount(12, $result['stockIn']);
        $this->assertCount(12, $result['stockOut']);
        // First label is the start of (now - 11 months).
        $this->assertSame(
            now()->subMonths(11)->startOfMonth()->format('Y-m'),
            $result['labels'][0],
        );
        $this->assertSame(now()->startOfMonth()->format('Y-m'), $result['labels'][11]);
        // All zeros when no movements exist.
        $this->assertSame(array_fill(0, 12, 0.0), $result['stockIn']);
        $this->assertSame(array_fill(0, 12, 0.0), $result['stockOut']);
    }

    public function test_monthly_stock_movements_aggregates_inbound_and_outbound(): void
    {
        $part = Part::factory()->create(['workshop_id' => $this->workshop->id]);

        // Receipt three months ago: +10 in.
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->actor->id,
            'type' => StockMovementType::Receipt,
            'quantity' => 10,
            'occurred_at' => now()->subMonths(3)->startOfMonth()->addDay(),
        ]);
        // Issue two months ago: -4 out.
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->actor->id,
            'type' => StockMovementType::Issue,
            'quantity' => -4,
            'occurred_at' => now()->subMonths(2)->startOfMonth()->addDay(),
        ]);

        $result = $this->service->monthlyStockMovements($this->workshop->id);

        $targetIn = now()->subMonths(3)->startOfMonth()->format('Y-m');
        $targetOut = now()->subMonths(2)->startOfMonth()->format('Y-m');

        $idxIn = array_search($targetIn, $result['labels'], true);
        $idxOut = array_search($targetOut, $result['labels'], true);

        $this->assertSame(10.0, (float) $result['stockIn'][$idxIn]);
        $this->assertSame(4.0, (float) $result['stockOut'][$idxOut]);
    }

    public function test_inventory_quantity_by_category_excludes_zero_rows(): void
    {
        $catA = PartCategory::factory()->create(['workshop_id' => $this->workshop->id, 'name' => 'Brakes']);
        $catB = PartCategory::factory()->create(['workshop_id' => $this->workshop->id, 'name' => 'Filters']);
        $partA = Part::factory()->create(['workshop_id' => $this->workshop->id, 'category_id' => $catA->id]);
        $partB = Part::factory()->create(['workshop_id' => $this->workshop->id, 'category_id' => $catB->id]);

        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $partA->id,
            'bin_id' => $this->bin->id,
            'quantity' => 12,
            'cost_price' => 5,
        ]);
        // Zero-quantity bucket must be excluded.
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $partB->id,
            'bin_id' => $this->bin->id,
            'quantity' => 0,
            'cost_price' => 9,
        ]);

        $result = $this->service->inventoryQuantityByCategory($this->workshop->id);

        $this->assertSame(['Brakes'], $result['labels']);
        $this->assertSame([12.0], $result['values']);
    }

    public function test_stock_value_by_category_ranks_descending_and_respects_limit(): void
    {
        $cat = PartCategory::factory()->create(['workshop_id' => $this->workshop->id, 'name' => 'Top Category']);
        $part = Part::factory()->create(['workshop_id' => $this->workshop->id, 'category_id' => $cat->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'quantity' => 5,
            'cost_price' => 100, // $500
        ]);

        $result = $this->service->stockValueByCategory($this->workshop->id, limit: 5);

        $this->assertLessThanOrEqual(5, count($result['labels']));
        $this->assertGreaterThanOrEqual(1, count($result['labels']));
        $this->assertSame(500.0, (float) $result['total']);
        // Highest value first.
        $this->assertSame(500.0, (float) $result['values'][0]);
    }

    public function test_battery_quantity_by_type_returns_null_when_empty(): void
    {
        $this->assertNull($this->service->batteryQuantityByType($this->workshop->id));
    }

    public function test_battery_quantity_by_type_groups_by_battery_type(): void
    {
        $agm = Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_type' => 'agm',
        ]);
        $lead = Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_type' => 'lead_acid',
        ]);
        BatteryInventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_id' => $agm->id,
            'bin_id' => $this->bin->id,
            'quantity' => 4,
        ]);
        BatteryInventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'battery_id' => $lead->id,
            'bin_id' => $this->bin->id,
            'quantity' => 7,
        ]);

        $result = $this->service->batteryQuantityByType($this->workshop->id);

        $this->assertNotNull($result);
        // Sorted descending by quantity.
        $this->assertSame('Lead Acid', $result['labels'][0]);
        $this->assertSame(7.0, (float) $result['values'][0]);
        $this->assertSame('Agm', $result['labels'][1]);
        $this->assertSame(4.0, (float) $result['values'][1]);
    }

    public function test_lubricant_quantity_by_type_returns_null_when_empty(): void
    {
        $this->assertNull($this->service->lubricantQuantityByType($this->workshop->id));
    }

    public function test_tool_quantity_by_category_groups_by_category_name(): void
    {
        $cat = ToolCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Power Tools',
        ]);
        Tool::factory()->count(3)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $cat->id,
        ]);
        Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => null, // uncategorised
        ]);

        $result = $this->service->toolQuantityByCategory($this->workshop->id);

        $this->assertNotNull($result);
        $labels = array_flip($result['labels']);
        $this->assertArrayHasKey('Power Tools', $labels);
        $this->assertArrayHasKey('Uncategorized', $labels);
        $this->assertSame(3.0, (float) $result['values'][$labels['Power Tools']]);
        $this->assertSame(1.0, (float) $result['values'][$labels['Uncategorized']]);
    }

    public function test_tool_quantity_by_category_returns_null_when_empty(): void
    {
        $this->assertNull($this->service->toolQuantityByCategory($this->workshop->id));
    }

    public function test_recent_stock_movements_orders_newest_first_and_caps_at_limit(): void
    {
        $part = Part::factory()->create(['workshop_id' => $this->workshop->id]);

        // Insert 12 movements spaced one minute apart; the method should
        // return the latest 10 in descending order.
        for ($i = 0; $i < 12; $i++) {
            StockMovement::create([
                'workshop_id' => $this->workshop->id,
                'part_id' => $part->id,
                'bin_id' => $this->bin->id,
                'user_id' => $this->actor->id,
                'type' => StockMovementType::Receipt,
                'quantity' => 1,
                'occurred_at' => now()->subMinutes(20 - $i),
            ]);
        }

        $rows = $this->service->recentStockMovements($this->workshop->id, limit: 10);

        $this->assertCount(10, $rows);
        $this->assertSame('part', $rows[0]['source']);
        $this->assertSame('in', $rows[0]['direction']);

        // Newest first: the first row should be later than the last row.
        $this->assertGreaterThan(
            $rows[9]['date']->timestamp,
            $rows[0]['date']->timestamp,
        );
        $this->assertSame($this->actor->name, $rows[0]['user_name']);
    }

    public function test_recent_stock_movements_returns_empty_array_when_no_data(): void
    {
        $this->assertSame([], $this->service->recentStockMovements($this->workshop->id));
    }

    public function test_global_inventory_value_aggregates_across_workshops(): void
    {
        // Bucket in this workshop.
        $part = Part::factory()->create(['workshop_id' => $this->workshop->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'quantity' => 4,
            'cost_price' => 50, // $200
        ]);

        // Bucket in another workshop — should also be summed by global rollup.
        $otherWorkshop = Workshop::factory()->create();
        $otherBin = BinLocation::factory()->create(['workshop_id' => $otherWorkshop->id]);
        $otherPart = Part::factory()->create(['workshop_id' => $otherWorkshop->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $otherWorkshop->id,
            'part_id' => $otherPart->id,
            'bin_id' => $otherBin->id,
            'quantity' => 6,
            'cost_price' => 25, // $150
        ]);

        $global = $this->service->globalInventoryValue();
        $this->assertSame(350.0, $global['inventory_value']);
        $this->assertSame(2, $global['items_count']);
    }
}
