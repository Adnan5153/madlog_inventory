<?php

namespace Tests\Feature\Admin;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CSV export coverage for the four report endpoints. Confirms that:
 *
 * - Admins can download each export.
 * - The response is `text/csv` with a `Content-Disposition` header
 *   for the right filename.
 * - The body contains the documented header row.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Workshop $workshop;

    protected Part $part;

    protected InventoryItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->workshop = Workshop::factory()->create();
        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);
        $this->part = Part::factory()->create(['workshop_id' => $this->workshop->id]);
        $this->item = InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'quantity' => 5,
            'cost_price' => 4,
        ]);
        StockMovement::create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $this->part->id,
            'type' => StockMovementType::ManualAdjustment,
            'quantity' => 5,
            'reason' => 'seed',
            'occurred_at' => now(),
        ]);
    }

    public function test_inventory_valuation_export_returns_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/inventory-valuation/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('inventory-valuation.csv', (string) $response->headers->get('Content-Disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('Part', explode("\n", $body)[0]);
        $this->assertStringContainsString('Value', explode("\n", $body)[0]);
    }

    public function test_low_stock_export_returns_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/low-stock/export');

        $response->assertOk();
        $this->assertStringContainsString('low-stock.csv', (string) $response->headers->get('Content-Disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('On hand', explode("\n", $body)[0]);
    }

    public function test_movement_history_export_returns_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/movement-history/export');

        $response->assertOk();
        $this->assertStringContainsString('movement-history.csv', (string) $response->headers->get('Content-Disposition'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('Occurred', explode("\n", $body)[0]);
    }

    public function test_top_consumed_export_returns_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/admin/reports/top-consumed/export');

        $response->assertOk();
        $this->assertStringContainsString('top-consumed.csv', (string) $response->headers->get('Content-Disposition'));
    }
}
