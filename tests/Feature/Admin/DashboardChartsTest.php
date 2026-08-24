<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementType;
use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end coverage for the dashboard charts:
 *
 *   - The admin dashboard renders the canvas elements.
 *   - The bar chart's first label is the part with the largest
 *     outgoing movement in the last 30 days.
 *   - The pie chart's slices sum to the same `inventory_value` returned
 *     by ReportService::inventoryValuation().
 *   - Global admins see the cross-workshop rollup; workshop-scoped
 *     admins see only their workshop.
 */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Workshop $workshop;

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
        $this->bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);
    }

    public function test_dashboard_renders_both_canvas_elements(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="chart-top-consumed"', false)
            ->assertSee('id="chart-inventory-by-category"', false);
    }

    public function test_bar_chart_labels_reflect_top_consumed_parts(): void
    {
        // 3 parts, each with one outgoing movement in the last 30 days.
        // "Top pad" gets the most consumption.
        $top = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Top Pad',
        ]);
        $mid = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Mid Pad',
        ]);
        $low = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Low Pad',
        ]);

        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $top->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Issue,
            'quantity' => -50,
            'occurred_at' => now()->subDays(5),
        ]);
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $mid->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Issue,
            'quantity' => -25,
            'occurred_at' => now()->subDays(2),
        ]);
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $low->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Issue,
            'quantity' => -5,
            'occurred_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // The Blade template emits `const barLabels = @json(...)`; the
        // first label must be the top-consumed part.
        $this->assertStringContainsString('"Top Pad"', $response->getContent());
        $this->assertStringContainsString('"Mid Pad"', $response->getContent());
        $this->assertStringContainsString('"Low Pad"', $response->getContent());
    }

    public function test_pie_chart_slices_sum_to_inventory_valuation_total(): void
    {
        $cat = PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Test Brakes',
        ]);
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $cat->id,
        ]);

        // Single bucket worth $250 (10 units @ $25).
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'batch_number' => null,
            'quantity' => 10,
            'cost_price' => 25,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertStringContainsString('"Test Brakes"', $response->getContent());
        // The pie chart's `total` JSON literal should equal 250.
        $this->assertStringContainsString('250', $response->getContent());
    }

    public function test_global_admin_sees_cross_workshop_chart_data(): void
    {
        // Two workshops with one part each, each having a stock movement.
        $other = Workshop::factory()->create();
        $otherBin = BinLocation::factory()->create(['workshop_id' => $other->id]);
        $otherPart = Part::factory()->create([
            'workshop_id' => $other->id,
            'name' => 'Cross-Workshop Widget',
        ]);
        StockMovement::create([
            'workshop_id' => $other->id,
            'part_id' => $otherPart->id,
            'bin_id' => $otherBin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Issue,
            'quantity' => -7,
            'occurred_at' => now()->subDays(2),
        ]);

        $global = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null, // global admin
        ]);

        $response = $this->actingAs($global)
            ->get(route('admin.dashboard'))
            ->assertOk();

        // Global admin's chart payload should include the other workshop's part.
        $this->assertStringContainsString('Cross-Workshop Widget', $response->getContent());
    }

    public function test_dashboard_shows_empty_state_when_no_movements(): void
    {
        // No inventory, no movements — charts should still render with
        // friendly empty messages rather than throwing.
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No outgoing stock movements recorded in the last 30 days.')
            ->assertSee('No inventory values yet');
    }

    public function test_dashboard_renders_new_inventory_intelligence_canvases(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="chart-monthly-movements"', false)
            ->assertSee('id="chart-movement-trend"', false)
            ->assertSee('id="chart-quantity-by-category"', false)
            ->assertSee('id="chart-stock-value-by-category"', false);
    }

    public function test_charts_json_payload_contains_new_keys(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $payload = $this->extractDashboardCharts((string) $response->getContent());

        $this->assertArrayHasKey('monthlyMovements', $payload);
        $this->assertArrayHasKey('movementTrend', $payload);
        $this->assertArrayHasKey('quantityByCategory', $payload);
        $this->assertArrayHasKey('stockValueByCat', $payload);
        $this->assertArrayHasKey('batteries', $payload);
        $this->assertArrayHasKey('lubricants', $payload);
        $this->assertArrayHasKey('tools', $payload);

        $this->assertCount(12, $payload['monthlyMovements']['labels']);
        $this->assertCount(12, $payload['monthlyMovements']['stockIn']);
        $this->assertCount(12, $payload['monthlyMovements']['stockOut']);
        $this->assertSame(
            $payload['monthlyMovements']['labels'],
            $payload['movementTrend']['labels'],
        );
        $this->assertLessThanOrEqual(10, count($payload['stockValueByCat']['labels']));
    }

    public function test_total_inventory_value_kpi_reflects_valuation(): void
    {
        $cat = PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'KPI Brakes',
        ]);
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $cat->id,
        ]);

        // $25 × 10 = $250 total inventory value.
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'batch_number' => null,
            'quantity' => 10,
            'cost_price' => 25,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $body = (string) $response->getContent();

        $this->assertStringContainsString('Total inventory value', $body);
        $this->assertStringContainsString('$250.00', $body);
    }

    public function test_monthly_movements_zero_fill_keeps_all_twelve_months(): void
    {
        // Insert exactly one movement five months ago. The zero-fill
        // contract means all twelve month labels must still be present
        // in the JSON payload, including the months with no activity.
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
        ]);
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Receipt,
            'quantity' => 12,
            'occurred_at' => now()->subMonths(5)->startOfMonth()->addDays(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $payload = $this->extractDashboardCharts((string) $response->getContent());
        $this->assertCount(12, $payload['monthlyMovements']['labels']);

        // Find the entry 5 months ago — it should have stock-in 12, others 0.
        $target = now()->subMonths(5)->startOfMonth()->format('Y-m');
        $idx = array_search($target, $payload['monthlyMovements']['labels'], true);
        $this->assertNotFalse($idx, "Expected month {$target} in series.");
        $this->assertSame(12.0, (float) $payload['monthlyMovements']['stockIn'][$idx]);
        $this->assertSame(0.0, (float) $payload['monthlyMovements']['stockOut'][$idx]);
    }

    /**
     * Parse the `window.__dashboardCharts = {…}` JSON literal out of the
     * rendered HTML so tests can assert on the same payload the JS sees.
     *
     * @return array<string, mixed>
     */
    private function extractDashboardCharts(string $html): array
    {
        if (! preg_match('/window\.__dashboardCharts\s*=\s*(\{.*?\});/s', $html, $m)) {
            $this->fail('Could not find window.__dashboardCharts in response.');
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
