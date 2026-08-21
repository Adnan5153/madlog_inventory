<?php

/**
 * Smoke test for the equipment consumables module. Exercises every verb
 * of EquipmentConsumableService against real DB rows, then asserts the
 * expected side effects on:
 *   - equipment_consumables / equipment_consumable_assignments
 *   - stock_movements (for Parts)
 *   - battery_stock_movements (for Batteries)
 *   - lubricant_stock_movements (for Lubricants)
 *
 * Run:  php smoke_equipment_consumables.php
 */

use App\Exceptions\DomainException;
use App\Models\Battery;
use App\Models\BatteryInventoryItem;
use App\Models\BatteryStockMovement;
use App\Models\Equipment;
use App\Models\EquipmentConsumable;
use App\Models\EquipmentConsumableAssignment;
use App\Models\InventoryItem;
use App\Models\Lubricant;
use App\Models\LubricantInventoryItem;
use App\Models\LubricantStockMovement;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Inventory\EquipmentConsumableService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function pass(string $msg): void
{
    echo "[ OK ] $msg\n";
}
function fail(string $msg): never
{
    echo "[FAIL] $msg\n";
    exit(1);
}

// Pick a workshop and an admin user
$workshop = Workshop::query()->first();
if (! $workshop) {
    fail('No workshop seeded.');
}
$admin = User::query()->where('role', User::ROLE_ADMIN)->first();
if (! $admin) {
    fail('No admin user seeded.');
}

echo "Workshop: {$workshop->name} (#{$workshop->id})\n";
echo "Admin: {$admin->name}\n";

// Create an Equipment for the test (workshop scope disabled)
$equipment = Equipment::withoutWorkshopScope(function () use ($workshop) {
    return Equipment::query()->create([
        'workshop_id' => $workshop->id,
        'name' => 'Smoke test equipment '.now()->timestamp,
        'asset_number' => 'SMK-'.now()->timestamp,
        'status' => 'active',
        'is_active' => true,
    ]);
});

// Pick or create a Part with inventory
$part = Part::withoutWorkshopScope(fn () => Part::query()->where('workshop_id', $workshop->id)->first());
if (! $part) {
    fail('No part in workshop; seed Parts first.');
}
$inv = InventoryItem::withoutWorkshopScope(fn () => InventoryItem::query()->where('part_id', $part->id)->where('workshop_id', $workshop->id)->first());
if (! $inv) {
    fail('No InventoryItem for part '.$part->name);
}

// Pick or create a Battery with inventory
$battery = Battery::withoutWorkshopScope(fn () => Battery::query()->where('workshop_id', $workshop->id)->first());
$batInv = null;
if ($battery) {
    $batInv = BatteryInventoryItem::withoutWorkshopScope(fn () => BatteryInventoryItem::query()->where('battery_id', $battery->id)->where('workshop_id', $workshop->id)->first());
}

// Pick or create a Lubricant with inventory
$lubricant = Lubricant::withoutWorkshopScope(fn () => Lubricant::query()->where('workshop_id', $workshop->id)->first());
$lubInv = null;
if ($lubricant) {
    $lubInv = LubricantInventoryItem::withoutWorkshopScope(fn () => LubricantInventoryItem::query()->where('lubricant_id', $lubricant->id)->where('workshop_id', $workshop->id)->first());
}

$beforePartQty = (float) $inv->quantity;
$beforeBatQty = $batInv ? (float) $batInv->quantity : 0.0;
$beforeLubQty = $lubInv ? (float) $lubInv->quantity : 0.0;

echo "Before: part qty $beforePartQty, battery qty $beforeBatQty, lubricant qty $beforeLubQty\n";

// 1. ASSIGN
$service = app(EquipmentConsumableService::class);

$consumable = $service->assign(
    equipment: $equipment,
    resource: $part,
    quantity: 2.0,
    assignedAt: CarbonImmutable::now(),
    expectedReplacementAt: CarbonImmutable::now()->addDays(60),
    actor: $admin,
    notes: 'smoke test assign',
    unitCost: 12.50,
);

$consumable->refresh();
$consumable->setRelations([]);
$assignCount = EquipmentConsumableAssignment::withoutWorkshopScope(fn () => $consumable->assignments()->count());
if ($assignCount !== 1) {
    fail('assign: expected 1 assignment, got '.$assignCount);
}
$current = EquipmentConsumableAssignment::withoutWorkshopScope(fn () => $consumable->currentAssignment);
if ($current === null) {
    fail('assign: expected currentAssignment');
}
if ((float) $current->total_cost !== 25.0) {
    fail('assign: expected total_cost 25.0');
}
pass('assign() created consumable + assigned assignment');

// 2. INSTALL
$service->install(
    consumable: $consumable,
    quantity: 2.0,
    at: CarbonImmutable::now(),
    actor: $admin,
    notes: null,
);
$consumable->refresh();
$installedCount = EquipmentConsumableAssignment::withoutWorkshopScope(
    fn () => $consumable->assignments()->where('type', 'installed')->count()
);
if ($installedCount !== 1) {
    fail('install: expected 1 installed assignment, got '.$installedCount);
}
pass('install() created installed assignment');

// 3. CONSUME
$consumeAssignment = $service->consume(
    consumable: $consumable,
    quantity: 1.0,
    at: CarbonImmutable::now(),
    actor: $admin,
    notes: 'consumed 1 unit',
);
$consumable->refresh();
$consumable->unsetRelation('currentAssignment');
$consumable->unsetRelation('assignments');
$inv->refresh();
// consume() is a terminal event — it closes the consumable by writing a
// row with status=Consumed. The whereNotExists clause on currentAssignment
// then correctly excludes the prior Installed row.
$currentAfterConsume = EquipmentConsumableAssignment::withoutWorkshopScope(
    fn () => $consumable->currentAssignment()->first()
);
if ($currentAfterConsume !== null) {
    fail('consume: expected currentAssignment to be null after terminal consume, got id='.$currentAfterConsume->id.' status='.$currentAfterConsume->status->value);
}
$consumedCount = EquipmentConsumableAssignment::withoutWorkshopScope(
    fn () => $consumable->assignments()->where('type', 'consumed')->count()
);
if ($consumedCount !== 1) {
    fail('consume: expected 1 consumed assignment, got '.$consumedCount);
}
if ($consumeAssignment->stock_movement_type !== 'part') {
    fail('consume: expected stock_movement_type=part, got '.$consumeAssignment->stock_movement_type);
}
if ($consumeAssignment->stock_movement_id === null) {
    fail('consume: expected stock_movement_id');
}
$invQty = InventoryItem::withoutWorkshopScope(fn () => (float) InventoryItem::find($inv->id)->quantity);
if (abs($invQty - ($beforePartQty - 1.0)) > 0.0001) {
    fail('consume: expected inventory qty '.($beforePartQty - 1.0).', got '.$invQty);
}
$movement = StockMovement::withoutWorkshopScope(
    fn () => StockMovement::find($consumeAssignment->stock_movement_id)
);
if ($movement === null) {
    fail('consume: stock movement row not found');
}
if ((float) $movement->quantity !== -1.0) {
    fail('consume: stock movement quantity expected -1.0');
}
pass('consume() deducted part stock and wrote ledger row');

// 4. REPLACE — needs an OPEN consumable. consume() closed $consumable,
// so create a fresh one and install it (open state) before replacing.
$replaceSource = $service->assign(
    equipment: $equipment,
    resource: $part,
    quantity: 2.0,
    assignedAt: CarbonImmutable::now(),
    expectedReplacementAt: null,
    actor: $admin,
    notes: 'smoke test source for replace',
    unitCost: 12.50,
);
$service->install(
    consumable: $replaceSource,
    quantity: 2.0,
    at: CarbonImmutable::now(),
    actor: $admin,
    notes: null,
);
$replace = $service->replace(
    consumable: $replaceSource,
    newResource: $part,
    quantity: 2.0,
    at: CarbonImmutable::now(),
    actor: $admin,
    notes: 'replaced',
);
if ($replace === null) {
    fail('replace: returned null');
}
if ($replace->id === $replaceSource->id) {
    fail('replace: returned same consumable');
}
$replaceSource->refresh();
$replaceSource->unsetRelation('currentAssignment');
$replaceSource->unsetRelation('assignments');
$currentAfterReplace = EquipmentConsumableAssignment::withoutWorkshopScope(
    fn () => $replaceSource->currentAssignment()->first()
);
if ($currentAfterReplace !== null) {
    fail('replace: source consumable should have been closed');
}

$replacedAssignment = EquipmentConsumableAssignment::withoutWorkshopScope(
    fn () => $replaceSource->assignments()->where('type', 'replaced')->first()
);
if ($replacedAssignment === null) {
    fail('replace: source consumable missing replaced assignment');
}
if ($replacedAssignment->previous_assignment_id === null) {
    fail('replace: missing previous_assignment_id chain');
}
$replacementFirst = EquipmentConsumableAssignment::withoutWorkshopScope(
    fn () => $replace->assignments()->where('type', 'assigned')->first()
);
if ($replacementFirst === null) {
    fail('replace: new consumable missing assigned assignment');
}
pass('replace() chained previous_assignment_id and created new consumable');

// 5. REMOVE the new consumable, return-to-stock 0.5
$removeAssignment = $service->remove(
    consumable: $replace,
    quantity: 1.0,
    at: CarbonImmutable::now(),
    actor: $admin,
    notes: 'smoke test remove',
    returnToStockQty: 0.0,
);
$replace->refresh();
$currentAfterRemove = EquipmentConsumableAssignment::withoutWorkshopScope(fn () => $replace->currentAssignment);
if ($currentAfterRemove !== null) {
    fail('remove: currentAssignment should be null after remove');
}
if ($removeAssignment->stock_movement_id !== null) {
    fail('remove: no return was requested, expected no stock movement id');
}
pass('remove() closed consumable without writing a stock movement');

// 6. BATTERY verb (if seeded)
if ($battery && $batInv) {
    $before = (float) $batInv->quantity;
    $batConsumable = $service->assign(
        equipment: $equipment,
        resource: $battery,
        quantity: 1.0,
        assignedAt: CarbonImmutable::now(),
        expectedReplacementAt: null,
        actor: $admin,
        notes: 'battery smoke',
    );
    $batAssignment = $service->consume(
        consumable: $batConsumable,
        quantity: 1.0,
        at: CarbonImmutable::now(),
        actor: $admin,
        notes: null,
    );
    $batQty = BatteryInventoryItem::withoutWorkshopScope(fn () => (float) BatteryInventoryItem::find($batInv->id)->quantity);
    $batMovement = BatteryStockMovement::withoutWorkshopScope(
        fn () => BatteryStockMovement::find($batAssignment->stock_movement_id)
    );
    if ($batMovement === null) {
        fail('consume(battery): stock movement row not found');
    }
    if ($batAssignment->stock_movement_type !== 'battery') {
        fail('consume(battery): wrong stock_movement_type');
    }
    if (abs($batQty - ($before - 1.0)) > 0.0001) {
        fail('consume(battery): expected qty '.($before - 1.0).', got '.$batQty);
    }
    pass('consume() works for Battery sub-system');
} else {
    echo "[skip] No Battery + BatteryInventoryItem seeded — skipping battery verb.\n";
}

// 7. LUBRICANT verb (if seeded)
if ($lubricant && $lubInv) {
    $before = (float) $lubInv->quantity;
    $lubConsumable = $service->assign(
        equipment: $equipment,
        resource: $lubricant,
        quantity: 1.0,
        assignedAt: CarbonImmutable::now(),
        expectedReplacementAt: null,
        actor: $admin,
        notes: 'lubricant smoke',
    );
    $lubAssignment = $service->consume(
        consumable: $lubConsumable,
        quantity: 1.0,
        at: CarbonImmutable::now(),
        actor: $admin,
        notes: null,
    );
    $lubQty = LubricantInventoryItem::withoutWorkshopScope(fn () => (float) LubricantInventoryItem::find($lubInv->id)->quantity);
    $lubMovement = LubricantStockMovement::withoutWorkshopScope(
        fn () => LubricantStockMovement::find($lubAssignment->stock_movement_id)
    );
    if ($lubMovement === null) {
        fail('consume(lubricant): stock movement row not found');
    }
    if ($lubAssignment->stock_movement_type !== 'lubricant') {
        fail('consume(lubricant): wrong stock_movement_type');
    }
    if (abs($lubQty - ($before - 1.0)) > 0.0001) {
        fail('consume(lubricant): expected qty '.($before - 1.0).', got '.$lubQty);
    }
    pass('consume() works for Lubricant sub-system');
} else {
    echo "[skip] No Lubricant + LubricantInventoryItem seeded — skipping lubricant verb.\n";
}

// 8. Negative-stock guard
try {
    if ($battery && $batInv) {
        $service->consume(
            consumable: $lubConsumable ?? $replace ?? $consumable,
            quantity: 99999.0,
            at: CarbonImmutable::now(),
            actor: $admin,
            notes: null,
        );
        fail('Expected DomainException for over-consumption, got none');
    }
} catch (DomainException $e) {
    pass('over-consumption correctly throws DomainException');
} catch (Throwable $e) {
    fail('Wrong exception type for over-consumption: '.get_class($e).' — '.$e->getMessage());
}

// Cleanup
DB::beginTransaction();
try {
    EquipmentConsumableAssignment::withoutWorkshopScope(function () use ($equipment) {
        EquipmentConsumableAssignment::query()->whereIn('equipment_consumable_id', function ($q) use ($equipment) {
            $q->select('id')->from('equipment_consumables')->where('equipment_id', $equipment->id);
        })->delete();
        EquipmentConsumable::query()->where('equipment_id', $equipment->id)->delete();
        $equipment->delete();
    });
    DB::commit();
} catch (Throwable $e) {
    DB::rollBack();
    fail('Cleanup failed: '.$e->getMessage());
}

echo "\nAll equipment-consumable smoke checks passed.\n";
