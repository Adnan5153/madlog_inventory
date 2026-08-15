<?php

namespace Tests\Unit\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Inventory\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit coverage for the two chart-shaped aggregation methods:
 *
 *   - ReportService::topConsumedForChart()
 *   - ReportService::inventoryValueByCategory()
 */
class ReportServiceChartTest extends TestCase
{
    use RefreshDatabase;

    protected User $globalAdmin;

    protected Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        // Global admin (workshop_id = null) so the WorkshopScope returns
        // every row across every workshop — same pattern as the other
        // unit tests.
        $this->globalAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null,
        ]);
        $this->actingAs($this->globalAdmin);

        $this->workshop = Workshop::factory()->create();
    }

    public function test_top_consumed_for_chart_returns_labels_and_values(): void
    {
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);
        $part = Part::factory()->create(['workshop_id' => $this->workshop->id]);

        // Outgoing movement: -10 within the last 30 days.
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $bin->id,
            'user_id' => $this->globalAdmin->id,
            'type' => StockMovementType::Issue,
            'quantity' => -10,
            'occurred_at' => now()->subDays(5),
        ]);

        /** @var ReportService $svc */
        $svc = app(ReportService::class);
        $result = $svc->topConsumedForChart($this->workshop->id);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('values', $result);
        $this->assertCount(1, $result['labels']);
        $this->assertSame($part->name, $result['labels'][0]);
        $this->assertEqualsWithDelta(10.0, $result['values'][0], 0.001);
    }

    public function test_top_consumed_for_chart_empty_returns_empty_arrays(): void
    {
        /** @var ReportService $svc */
        $svc = app(ReportService::class);
        $result = $svc->topConsumedForChart($this->workshop->id);

        $this->assertSame(['labels' => [], 'values' => []], $result);
    }

    public function test_inventory_value_by_category_groups_by_part_category(): void
    {
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);
        $catBrakes = PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Test Brakes',
        ]);
        $catFluids = PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Test Fluids',
        ]);

        $partA = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $catBrakes->id,
        ]);
        $partB = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $catFluids->id,
        ]);

        // Brakes bucket: 10 units @ $5 = $50.
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $partA->id,
            'bin_id' => $bin->id,
            'batch_number' => null,
            'quantity' => 10,
            'cost_price' => 5,
        ]);

        // Fluids bucket: 4 units @ $20 = $80.
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $partB->id,
            'bin_id' => $bin->id,
            'batch_number' => null,
            'quantity' => 4,
            'cost_price' => 20,
        ]);

        /** @var ReportService $svc */
        $svc = app(ReportService::class);
        $result = $svc->inventoryValueByCategory($this->workshop->id);

        $this->assertArrayHasKey('labels', $result);
        $this->assertArrayHasKey('values', $result);
        $this->assertArrayHasKey('total', $result);

        // Ordered DESC by value: Fluids (80) before Brakes (50).
        $this->assertSame(['Test Fluids', 'Test Brakes'], $result['labels']);
        $this->assertEqualsWithDelta(80.0, $result['values'][0], 0.001);
        $this->assertEqualsWithDelta(50.0, $result['values'][1], 0.001);
        $this->assertEqualsWithDelta(130.0, $result['total'], 0.001);
    }

    public function test_inventory_value_by_category_returns_empty_when_no_inventory(): void
    {
        /** @var ReportService $svc */
        $svc = app(ReportService::class);
        $result = $svc->inventoryValueByCategory($this->workshop->id);

        $this->assertSame([], $result['labels']);
        $this->assertSame([], $result['values']);
        $this->assertSame(0.0, $result['total']);
    }

    public function test_inventory_value_by_category_skips_parts_without_a_category(): void
    {
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => null, // uncategorised
        ]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $bin->id,
            'batch_number' => null,
            'quantity' => 100,
            'cost_price' => 10,
        ]);

        /** @var ReportService $svc */
        $svc = app(ReportService::class);
        $result = $svc->inventoryValueByCategory($this->workshop->id);

        $this->assertSame([], $result['labels']);
        $this->assertSame(0.0, $result['total']);
    }
}
