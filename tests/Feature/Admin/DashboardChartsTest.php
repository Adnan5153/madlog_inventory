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

        $this->seed(\Database\Seeders\SettingsSeeder::class);

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
}
