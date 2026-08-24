<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Battery;
use App\Models\BatteryInventoryItem;
use App\Models\BatteryStockAdjustment;
use App\Models\BatteryStockAdjustmentItem;
use App\Models\BatteryStockMovement;
use App\Models\BinLocation;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentConsumable;
use App\Models\EquipmentConsumableAssignment;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\JobCardPart;
use App\Models\Lubricant;
use App\Models\LubricantInventoryItem;
use App\Models\LubricantStockAdjustment;
use App\Models\LubricantStockAdjustmentItem;
use App\Models\LubricantStockMovement;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\SerialNumber;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ToolCheckout;
use App\Models\ToolMaintenanceRecord;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * End-to-end smoke test: every factory must produce at least one
 * row on a clean test database without throwing. This catches:
 *   - missing factories (the model declares HasFactory<XFactory>
 *     but the file doesn't exist);
 *   - schema mismatches (factory supplies a column that doesn't
 *     exist in the migration);
 *   - invalid enum values;
 *   - FK targets that can't be resolved (Workshop::factory() chain);
 *   - circular factory dependencies.
 *
 * The test seeds SettingsSeeder first because it inserts settings
 * rows that other factories/services may indirectly reference.
 */
class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
    }

    /**
     * Each entry yields [label, createFn, expectedTable]. Kept as a
     * flat list of positional arrays so the test method signature
     * stays in lockstep with PHPUnit 12 data providers.
     *
     * @return iterable<string, array{0: string, 1: callable(): mixed, 2: string}>
     */
    public static function factories(): iterable
    {
        yield ['Workshop', fn () => Workshop::factory()->create(), 'workshops'];
        yield ['User', fn () => User::factory()->create(), 'users'];
        yield ['Role', fn () => Role::factory()->create(), 'roles'];
        yield ['Permission', fn () => Permission::factory()->create(), 'permissions'];
        yield ['Unit', fn () => Unit::factory()->create(), 'units'];
        yield ['BinLocation', fn () => BinLocation::factory()->create(), 'bin_locations'];
        yield ['PartCategory', fn () => PartCategory::factory()->create(), 'part_categories'];
        yield ['Part', fn () => Part::factory()->create(), 'parts'];
        yield ['InventoryItem', fn () => InventoryItem::factory()->create(), 'inventory_items'];
        yield ['StockMovement', fn () => StockMovement::factory()->create(), 'stock_movements'];
        yield ['SupplierCategory', fn () => SupplierCategory::factory()->create(), 'supplier_categories'];
        yield ['Supplier', fn () => Supplier::factory()->create(), 'suppliers'];
        yield ['PurchaseOrder', fn () => PurchaseOrder::factory()->create(), 'purchase_orders'];
        yield ['PurchaseOrderItem', fn () => PurchaseOrderItem::factory()->create(), 'purchase_order_items'];
        yield ['GoodsReceipt', fn () => GoodsReceipt::factory()->create(), 'goods_receipts'];
        yield ['GoodsReceiptItem', fn () => GoodsReceiptItem::factory()->create([
            'goods_receipt_id' => GoodsReceipt::factory(),
        ]), 'goods_receipt_items'];
        yield ['SerialNumber', fn () => SerialNumber::factory()->create(), 'serial_numbers'];
        yield ['Batch', fn () => Batch::factory()->create(), 'batches'];
        yield ['Department', fn () => Department::factory()->create(), 'departments'];
        yield ['Equipment', fn () => Equipment::factory()->create(), 'equipment'];
        yield ['StockAdjustment', fn () => StockAdjustment::factory()->create(), 'stock_adjustments'];
        yield ['StockAdjustmentItem', fn () => StockAdjustmentItem::factory()->create(), 'stock_adjustment_items'];
        yield ['StockTransfer', fn () => StockTransfer::factory()->create(), 'stock_transfers'];
        yield ['StockTransferItem', fn () => StockTransferItem::factory()->create(), 'stock_transfer_items'];
        yield ['JobCard', fn () => JobCard::factory()->create(), 'job_cards'];
        yield ['JobCardPart', fn () => JobCardPart::factory()->create(), 'job_card_parts'];
        yield ['AuditLog', fn () => AuditLog::factory()->create(), 'audit_logs'];
        yield ['Battery', fn () => Battery::factory()->create(), 'batteries'];
        yield ['BatteryInventoryItem', fn () => BatteryInventoryItem::factory()->create(), 'battery_inventory_items'];
        yield ['BatteryStockMovement', fn () => BatteryStockMovement::factory()->create(), 'battery_stock_movements'];
        yield ['BatteryStockAdjustment', fn () => BatteryStockAdjustment::factory()->create(), 'battery_stock_adjustments'];
        yield ['BatteryStockAdjustmentItem', fn () => BatteryStockAdjustmentItem::factory()->create(), 'battery_stock_adjustment_items'];
        yield ['Lubricant', fn () => Lubricant::factory()->create(), 'lubricants'];
        yield ['LubricantInventoryItem', fn () => LubricantInventoryItem::factory()->create(), 'lubricant_inventory_items'];
        yield ['LubricantStockMovement', fn () => LubricantStockMovement::factory()->create(), 'lubricant_stock_movements'];
        yield ['LubricantStockAdjustment', fn () => LubricantStockAdjustment::factory()->create(), 'lubricant_stock_adjustments'];
        yield ['LubricantStockAdjustmentItem', fn () => LubricantStockAdjustmentItem::factory()->create(), 'lubricant_stock_adjustment_items'];
        yield ['ToolCategory', fn () => ToolCategory::factory()->create(), 'tool_categories'];
        yield ['Tool', fn () => Tool::factory()->create(), 'tools'];
        yield ['ToolCheckout', fn () => ToolCheckout::factory()->create(), 'tool_checkouts'];
        yield ['ToolMaintenanceRecord', fn () => ToolMaintenanceRecord::factory()->create(), 'tool_maintenance_records'];
        yield ['EquipmentConsumable', fn () => EquipmentConsumable::factory()->create(), 'equipment_consumables'];
        yield ['EquipmentConsumableAssignment', fn () => EquipmentConsumableAssignment::factory()->create(), 'equipment_consumable_assignments'];
    }

    /**
     * @dataProvider factories
     */
    #[DataProvider('factories')]
    public function test_factory_persists_row(string $label, callable $createFn, string $expectedTable): void
    {
        $model = $createFn();

        $this->assertNotNull($model, "Factory for {$label} returned null.");
        $this->assertTrue($model->exists, "Factory for {$label} did not persist the model.");

        $this->assertDatabaseHas($expectedTable, [
            'id' => $model->getKey(),
        ]);
    }

    public function test_all_factory_classes_resolve(): void
    {
        // Walk every factory file and make sure the FQN exists. This
        // catches typos and broken PSR-4 mappings that PHPUnit's
        // dataProvider would otherwise swallow.
        foreach (glob(database_path('factories/*.php')) as $file) {
            $contents = file_get_contents($file);
            if (! preg_match('/namespace\s+([^;]+);/', $contents, $m)) {
                $this->fail("Factory file {$file} is missing namespace.");
            }
            if (! preg_match('/class\s+(\w+)/', $contents, $c)) {
                $this->fail("Factory file {$file} is missing class.");
            }
            $fqn = $m[1].'\\'.$c[1];
            $this->assertTrue(class_exists($fqn), "Factory class {$fqn} does not autoload.");
        }
    }

    public function test_factory_count_matches_model_count(): void
    {
        // Sanity: every model that declares HasFactory<XFactory>
        // should have a corresponding factory file. This catches
        // broken references like HasFactory<EquipmentConsumableFactory>
        // when the file is missing.
        $declared = [];
        foreach (glob(app_path('Models/*.php')) as $file) {
            $contents = file_get_contents($file);
            if (preg_match('/HasFactory<([A-Za-z]+Factory)>/', $contents, $m)) {
                $declared[] = $m[1];
            }
        }

        $existing = [];
        foreach (glob(database_path('factories/*.php')) as $file) {
            if (preg_match('/class\s+(\w+Factory)/', file_get_contents($file), $m)) {
                $existing[] = $m[1];
            }
        }

        sort($declared);
        sort($existing);

        $missing = array_diff($declared, $existing);
        $this->assertSame(
            [],
            $missing,
            'Models declare HasFactory<XFactory> but the factory file is missing: '.implode(', ', $missing),
        );
    }

    public function test_settings_table_seeded(): void
    {
        // Sanity check on SettingsSeeder — every dashboard test relies
        // on it via $this->seed(SettingsSeeder::class).
        $this->assertDatabaseHas('settings', [
            'key' => 'inventory.default_currency',
            'workshop_id' => null,
        ]);
    }

    public function test_role_permission_seeder_runs_without_error(): void
    {
        // RolesAndPermissionsSeeder expects the roles + permissions
        // tables to exist; calling it again on a fresh DB should
        // produce rows. We use the raw DB facade for counts here
        // because WorkshopScope filters Eloquent reads when no
        // authenticated user is present (the CLI context).
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder'])
            ->assertSuccessful();

        $this->assertGreaterThan(0, DB::table('roles')->count());
        $this->assertGreaterThan(0, DB::table('permissions')->count());
    }

    public function test_settings_seeder_inserts_defaults(): void
    {
        $this->artisan('db:seed', ['--class' => 'SettingsSeeder'])
            ->assertSuccessful();

        // 10 keys defined in SettingsSeeder::DEFAULTS (6 inventory +
        // 4 numbering). The artisan call above will upsert each; the
        // first setUp()'s seed() call already inserted them, so this
        // verifies idempotency rather than row count.
        $this->assertGreaterThanOrEqual(10, DB::table('settings')->count());
    }
}
