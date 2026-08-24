<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementType;
use App\Models\Battery;
use App\Models\BinLocation;
use App\Models\Lubricant;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coverage for the new "Recent stock movements" panel that replaced the
 * previous AuditLog feed on the admin dashboard.
 *
 *   - The new heading appears; the legacy audit-log heading does not.
 *   - Inbound (Receipt) and outbound (Issue) movements render with the
 *     correct direction icon and signed quantity.
 *   - The empty state renders cleanly when no movements exist.
 */
class RecentStockMovementsTest extends TestCase
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

    public function test_recent_stock_movements_heading_replaces_audit_log(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $body = (string) $response->getContent();

        $this->assertStringContainsString('Recent stock movements', $body);
        $this->assertStringNotContainsString('Recent activity', $body);
    }

    public function test_signed_quantity_and_direction_icons_render(): void
    {
        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
        ]);

        // Outbound issue (negative quantity).
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Issue,
            'quantity' => -7,
            'occurred_at' => now()->subMinutes(5),
        ]);

        // Inbound receipt (positive quantity).
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Receipt,
            'quantity' => 3,
            'occurred_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $body = (string) $response->getContent();

        // Direction icons (in = arrow-down-left, out = arrow-up-right).
        $this->assertStringContainsString('bi-arrow-up-right', $body);
        $this->assertStringContainsString('bi-arrow-down-left', $body);

        // Signed quantity cells: the issue renders with "−" and the receipt with "+".
        $this->assertMatchesRegularExpression('/>\s*−7\.00\s*</', $body);
        $this->assertMatchesRegularExpression('/>\s*\+3\.00\s*</', $body);

        // The type badge shows the human-readable label.
        $this->assertStringContainsString('Issue', $body);
        $this->assertStringContainsString('Receipt', $body);
    }

    public function test_empty_state_renders_when_no_movements(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertStringContainsString(
            'No stock movements recorded yet',
            (string) $response->getContent(),
        );
    }

    public function test_battery_and_lubricant_movements_appear_in_recent_feed(): void
    {
        $battery = Battery::factory()->create([
            'workshop_id' => $this->workshop->id,
        ]);
        $battery->batteryInventoryItems()->create([
            'workshop_id' => $this->workshop->id,
            'bin_id' => $this->bin->id,
            'quantity' => 5,
            'cost_price' => 50,
        ]);
        $battery->batteryStockMovements()->create([
            'workshop_id' => $this->workshop->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Receipt,
            'quantity' => 5,
            'occurred_at' => now()->subMinute(),
        ]);

        $lubricant = Lubricant::factory()->create([
            'workshop_id' => $this->workshop->id,
        ]);
        $lubricant->lubricantInventoryItems()->create([
            'workshop_id' => $this->workshop->id,
            'bin_id' => $this->bin->id,
            'quantity' => 2,
            'cost_price' => 20,
        ]);
        $lubricant->lubricantStockMovements()->create([
            'workshop_id' => $this->workshop->id,
            'bin_id' => $this->bin->id,
            'user_id' => $this->admin->id,
            'type' => StockMovementType::Receipt,
            'quantity' => 2,
            'occurred_at' => now()->subSeconds(30),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $body = (string) $response->getContent();

        $this->assertStringContainsString($battery->name, $body);
        $this->assertStringContainsString($lubricant->name, $body);
    }
}
