<?php

namespace App\Services\Inventory;

use App\Enums\EquipmentConsumableStatus;
use App\Enums\EquipmentConsumableType;
use App\Enums\StockMovementType;
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
use App\Scopes\WorkshopScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Service for the Equipment consumables MVP.
 *
 * Five lifecycle verbs, each a single public method:
 *   assign   — register a resource to an equipment (no stock change).
 *   install  — record physical installation (no stock change).
 *   consume  — record usage; posts an Issue stock movement.
 *   replace  — close the current assignment and start a new consumable.
 *   remove   — take the resource off; optionally returns to stock.
 *
 * Stock movements are recorded through `StockMovementService::record()`
 * for Parts. For Battery and Lubricant there is no canonical service, so
 * the inline `DB::transaction` + `*StockMovement::create` pattern is
 * centralised in `recordBatteryMovement` / `recordLubricantMovement`
 * so future refactors land in one place.
 */
class EquipmentConsumableService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * Register a resource as tracked against an equipment.
     *
     * Creates one `EquipmentConsumable` row plus the first
     * `EquipmentConsumableAssignment` row (type=assigned, status=assigned).
     */
    public function assign(
        Equipment $equipment,
        Model $resource,
        float $quantity,
        CarbonImmutable $assignedAt,
        ?CarbonImmutable $expectedReplacementAt,
        ?User $actor,
        ?string $notes,
        ?int $binId = null,
        ?int $unitId = null,
        ?float $unitCost = null,
    ): EquipmentConsumable {
        $this->assertSupportedResource($resource);

        return DB::transaction(function () use (
            $equipment, $resource, $quantity, $assignedAt,
            $expectedReplacementAt, $actor, $notes, $binId, $unitId, $unitCost,
        ) {
            $consumable = EquipmentConsumable::withoutWorkshopScope(
                fn () => EquipmentConsumable::create([
                    'workshop_id' => $equipment->workshop_id,
                    'equipment_id' => $equipment->id,
                    'resource_type' => $resource::class,
                    'resource_id' => $resource->getKey(),
                    'assigned_at' => $assignedAt,
                    'expected_replacement_at' => $expectedReplacementAt,
                    'notes' => $notes,
                    'created_by' => $actor?->getKey(),
                    'updated_by' => $actor?->getKey(),
                ])
            );

            $this->createAssignmentRow(
                consumable: $consumable,
                type: EquipmentConsumableType::Assigned,
                status: EquipmentConsumableStatus::Assigned,
                quantity: $quantity,
                at: $assignedAt,
                actor: $actor,
                binId: $binId,
                unitId: $unitId,
                unitCost: $unitCost,
                notes: $notes,
            );

            return $consumable;
        });
    }

    /**
     * Record an installation event. No stock change.
     */
    public function install(
        EquipmentConsumable $consumable,
        float $quantity,
        CarbonImmutable $at,
        ?User $actor,
        ?string $notes,
        ?int $binId = null,
        ?int $unitId = null,
        ?float $unitCost = null,
    ): EquipmentConsumableAssignment {
        $this->assertOpen($consumable);

        return $this->createAssignmentRow(
            consumable: $consumable,
            type: EquipmentConsumableType::Installed,
            status: EquipmentConsumableStatus::Installed,
            quantity: $quantity,
            at: $at,
            actor: $actor,
            binId: $binId,
            unitId: $unitId,
            unitCost: $unitCost,
            notes: $notes,
        );
    }

    /**
     * Record consumption. Posts an Issue stock movement and writes the
     * assignment row with the matching stock_movement_type/id.
     */
    public function consume(
        EquipmentConsumable $consumable,
        float $quantity,
        CarbonImmutable $at,
        ?User $actor,
        ?string $notes,
        ?int $binId = null,
        ?int $unitId = null,
        ?float $unitCost = null,
    ): EquipmentConsumableAssignment {
        $this->assertOpen($consumable);

        if ($quantity <= 0) {
            throw new DomainException('Consumed quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $consumable, $quantity, $at, $actor, $notes, $binId, $unitId, $unitCost,
        ) {
            $resource = $this->resolveResource($consumable);
            $this->assertSupportedResource($resource);

            $equipment = $this->resolveEquipment($consumable);
            $reason = sprintf(
                'Consumed by equipment %s (#%s)',
                $equipment->name,
                $equipment->asset_number ?? $equipment->getKey(),
            );

            $movement = $this->recordStockIssue(
                resource: $resource,
                quantity: $quantity,
                actor: $actor,
                unitCost: $unitCost,
                reason: $reason,
                binId: $binId,
                reference: $consumable,
            );

            return $this->createAssignmentRow(
                consumable: $consumable,
                type: EquipmentConsumableType::Consumed,
                status: EquipmentConsumableStatus::Consumed,
                quantity: $quantity,
                at: $at,
                actor: $actor,
                binId: $binId,
                unitId: $unitId,
                unitCost: $unitCost,
                notes: $notes,
                stockMovementType: $movement['type'],
                stockMovementId: $movement['id'],
            );
        });
    }

    /**
     * Replace the current consumable with a new resource.
     *
     * Marks the previous assignment as `Replaced` (sets `previous_assignment_id`
     * on the new assignment to preserve the chain), creates a new
     * `EquipmentConsumable` linking to the new resource, and writes the
     * first `Assigned` assignment on the new consumable.
     *
     * No stock movement by default — the new resource is assumed to come
     * from the existing inventory book keeping. If the caller indicates
     * the replacement actually consumes stock, set `consumeQuantity` to
     * a positive value.
     */
    public function replace(
        EquipmentConsumable $consumable,
        Model $newResource,
        float $quantity,
        CarbonImmutable $at,
        ?User $actor,
        ?string $notes,
        ?int $binId = null,
        ?int $unitId = null,
        ?float $unitCost = null,
    ): EquipmentConsumable {
        $this->assertOpen($consumable);
        $this->assertSupportedResource($newResource);

        return DB::transaction(function () use (
            $consumable, $newResource, $quantity, $at, $actor, $notes, $binId, $unitId, $unitCost,
        ) {
            $currentAssignment = EquipmentConsumableAssignment::withoutWorkshopScope(
                fn () => $consumable->currentAssignment()->first()
            );
            $previousAssignmentId = $currentAssignment?->getKey();

            // Close the existing consumable by writing a 'replaced' assignment
            // that points back at the previous one.
            $this->createAssignmentRow(
                consumable: $consumable,
                type: EquipmentConsumableType::Replaced,
                status: EquipmentConsumableStatus::Removed,
                quantity: $quantity,
                at: $at,
                actor: $actor,
                binId: $binId,
                unitId: $unitId,
                unitCost: $unitCost,
                notes: $notes,
                previousAssignmentId: $previousAssignmentId,
            );

            // Now create the new consumable.
            $newConsumable = EquipmentConsumable::withoutWorkshopScope(
                fn () => EquipmentConsumable::create([
                    'workshop_id' => $consumable->workshop_id,
                    'equipment_id' => $consumable->equipment_id,
                    'resource_type' => $newResource::class,
                    'resource_id' => $newResource->getKey(),
                    'assigned_at' => $at,
                    'expected_replacement_at' => $consumable->expected_replacement_at,
                    'notes' => $notes,
                    'created_by' => $actor?->getKey(),
                    'updated_by' => $actor?->getKey(),
                ])
            );

            $this->createAssignmentRow(
                consumable: $newConsumable,
                type: EquipmentConsumableType::Assigned,
                status: EquipmentConsumableStatus::Assigned,
                quantity: $quantity,
                at: $at,
                actor: $actor,
                binId: $binId,
                unitId: $unitId,
                unitCost: $unitCost,
                notes: $notes,
            );

            return $newConsumable;
        });
    }

    /**
     * Take the consumable off the equipment. If `returnToStockQty` is
     * positive, the matching quantity is returned to the inventory bin
     * as a positive Return movement.
     */
    public function remove(
        EquipmentConsumable $consumable,
        float $quantity,
        CarbonImmutable $at,
        ?User $actor,
        ?string $notes,
        ?int $binId = null,
        ?int $unitId = null,
        ?float $unitCost = null,
        float $returnToStockQty = 0.0,
    ): EquipmentConsumableAssignment {
        $this->assertOpen($consumable);

        if ($quantity <= 0) {
            throw new DomainException('Removed quantity must be greater than zero.');
        }

        if ($returnToStockQty < 0) {
            throw new DomainException('Return-to-stock quantity cannot be negative.');
        }

        if ($returnToStockQty > $quantity) {
            throw new DomainException('Return-to-stock quantity cannot exceed the removed quantity.');
        }

        return DB::transaction(function () use (
            $consumable, $quantity, $at, $actor, $notes, $binId, $unitId, $unitCost, $returnToStockQty,
        ) {
            $resource = $this->resolveResource($consumable);
            $equipment = $this->resolveEquipment($consumable);

            $stockMovementType = null;
            $stockMovementId = null;

            if ($returnToStockQty > 0) {
                $this->assertSupportedResource($resource);

                $reason = sprintf(
                    'Returned from equipment %s (#%s)',
                    $equipment->name,
                    $equipment->asset_number ?? $equipment->getKey(),
                );

                $movement = $this->recordStockReturn(
                    resource: $resource,
                    quantity: $returnToStockQty,
                    actor: $actor,
                    unitCost: $unitCost,
                    reason: $reason,
                    binId: $binId,
                    reference: $consumable,
                );
                $stockMovementType = $movement['type'];
                $stockMovementId = $movement['id'];
            }

            return $this->createAssignmentRow(
                consumable: $consumable,
                type: EquipmentConsumableType::Removed,
                status: EquipmentConsumableStatus::Removed,
                quantity: $quantity,
                at: $at,
                actor: $actor,
                binId: $binId,
                unitId: $unitId,
                unitCost: $unitCost,
                notes: $notes,
                stockMovementType: $stockMovementType,
                stockMovementId: $stockMovementId,
            );
        });
    }

    // -----------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------

    /**
     * @return array{type: string, id: int}
     */
    private function recordStockIssue(
        Model $resource,
        float $quantity,
        ?User $actor,
        ?float $unitCost,
        string $reason,
        ?int $binId,
        Model $reference,
    ): array {
        $effectiveActor = $this->ensureActor($actor);

        if ($resource instanceof Part) {
            $item = $this->resolveInventoryItem($resource, $binId, $reference->workshop_id);

            $movement = $this->stockMovements->record(
                type: StockMovementType::Issue,
                item: $item,
                quantity: -abs($quantity),
                actor: $effectiveActor,
                unitCost: $unitCost,
                reason: $reason,
                reference: $reference,
            );

            return ['type' => 'part', 'id' => $movement->getKey()];
        }

        if ($resource instanceof Battery) {
            $item = $this->resolveBatteryInventoryItem($resource, $binId, $reference->workshop_id);

            $movement = $this->recordBatteryMovement(
                item: $item,
                quantity: -abs($quantity),
                actor: $effectiveActor,
                unitCost: $unitCost,
                type: StockMovementType::Issue,
                reason: $reason,
                reference: $reference,
            );

            return ['type' => 'battery', 'id' => $movement->getKey()];
        }

        if ($resource instanceof Lubricant) {
            $item = $this->resolveLubricantInventoryItem($resource, $binId, $reference->workshop_id);

            $movement = $this->recordLubricantMovement(
                item: $item,
                quantity: -abs($quantity),
                actor: $effectiveActor,
                unitCost: $unitCost,
                type: StockMovementType::Issue,
                reason: $reason,
                reference: $reference,
            );

            return ['type' => 'lubricant', 'id' => $movement->getKey()];
        }

        throw new DomainException('Unsupported resource type: '.$resource::class);
    }

    /**
     * @return array{type: string, id: int}
     */
    private function recordStockReturn(
        Model $resource,
        float $quantity,
        ?User $actor,
        ?float $unitCost,
        string $reason,
        ?int $binId,
        Model $reference,
    ): array {
        $effectiveActor = $this->ensureActor($actor);

        if ($resource instanceof Part) {
            $item = $this->resolveInventoryItem($resource, $binId, $reference->workshop_id);

            $movement = $this->stockMovements->record(
                type: StockMovementType::Return,
                item: $item,
                quantity: abs($quantity),
                actor: $effectiveActor,
                unitCost: $unitCost,
                reason: $reason,
                reference: $reference,
            );

            return ['type' => 'part', 'id' => $movement->getKey()];
        }

        if ($resource instanceof Battery) {
            $item = $this->resolveBatteryInventoryItem($resource, $binId, $reference->workshop_id);

            $movement = $this->recordBatteryMovement(
                item: $item,
                quantity: abs($quantity),
                actor: $effectiveActor,
                unitCost: $unitCost,
                type: StockMovementType::Return,
                reason: $reason,
                reference: $reference,
            );

            return ['type' => 'battery', 'id' => $movement->getKey()];
        }

        if ($resource instanceof Lubricant) {
            $item = $this->resolveLubricantInventoryItem($resource, $binId, $reference->workshop_id);

            $movement = $this->recordLubricantMovement(
                item: $item,
                quantity: abs($quantity),
                actor: $effectiveActor,
                unitCost: $unitCost,
                type: StockMovementType::Return,
                reason: $reason,
                reference: $reference,
            );

            return ['type' => 'lubricant', 'id' => $movement->getKey()];
        }

        throw new DomainException('Unsupported resource type: '.$resource::class);
    }

    private function recordBatteryMovement(
        BatteryInventoryItem $item,
        float $quantity,
        User $actor,
        ?float $unitCost,
        StockMovementType $type,
        string $reason,
        Model $reference,
    ): BatteryStockMovement {
        if ($quantity === 0.0) {
            throw new DomainException('Stock movement quantity cannot be zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $actor, $unitCost, $type, $reason, $reference) {
            $allowNegative = (bool) setting('inventory.allow_negative_stock', false, $item->workshop_id);
            $newQty = (float) $item->quantity + $quantity;
            if (! $allowNegative && $newQty < 0) {
                throw new DomainException(sprintf(
                    'Battery adjustment would drop on-hand below zero (current %.2f, change %.2f). Enable inventory.allow_negative_stock to allow.',
                    (float) $item->quantity,
                    $quantity,
                ));
            }

            $item->quantity = $newQty;
            $item->save();

            return BatteryStockMovement::create([
                'workshop_id' => $item->workshop_id,
                'battery_id' => $item->battery_id,
                'bin_id' => $item->bin_id,
                'user_id' => $actor->getKey(),
                'battery_inventory_item_id' => $item->getKey(),
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => $reference::class,
                'reference_id' => $reference->getKey(),
                'reason' => $reason,
                'occurred_at' => now(),
            ]);
        });
    }

    private function recordLubricantMovement(
        LubricantInventoryItem $item,
        float $quantity,
        User $actor,
        ?float $unitCost,
        StockMovementType $type,
        string $reason,
        Model $reference,
    ): LubricantStockMovement {
        if ($quantity === 0.0) {
            throw new DomainException('Stock movement quantity cannot be zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $actor, $unitCost, $type, $reason, $reference) {
            $allowNegative = (bool) setting('inventory.allow_negative_stock', false, $item->workshop_id);
            $newQty = (float) $item->quantity + $quantity;
            if (! $allowNegative && $newQty < 0) {
                throw new DomainException(sprintf(
                    'Lubricant adjustment would drop on-hand below zero (current %.2f, change %.2f). Enable inventory.allow_negative_stock to allow.',
                    (float) $item->quantity,
                    $quantity,
                ));
            }

            $item->quantity = $newQty;
            $item->save();

            return LubricantStockMovement::create([
                'workshop_id' => $item->workshop_id,
                'lubricant_id' => $item->lubricant_id,
                'bin_id' => $item->bin_id,
                'user_id' => $actor->getKey(),
                'lubricant_inventory_item_id' => $item->getKey(),
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => $reference::class,
                'reference_id' => $reference->getKey(),
                'reason' => $reason,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * Resolve an InventoryItem for a Part. Prefers the bin the caller
     * supplied, falling back to the part's default bin, falling back to
     * the most-recently stocked bin.
     */
    private function resolveInventoryItem(Part $part, ?int $binId, int $workshopId): InventoryItem
    {
        $query = InventoryItem::query()
            ->withoutGlobalScope(WorkshopScope::class)
            ->where('workshop_id', $workshopId)
            ->where('part_id', $part->getKey());

        if ($binId !== null) {
            $item = (clone $query)->where('bin_id', $binId)->orderByDesc('id')->first();
            if ($item !== null) {
                return $item;
            }
        }

        if ($part->bin_location_id !== null) {
            $item = (clone $query)->where('bin_id', $part->bin_location_id)->orderByDesc('id')->first();
            if ($item !== null) {
                return $item;
            }
        }

        $item = $query->orderByDesc('id')->first();
        if ($item === null) {
            throw new DomainException(
                "No inventory bucket exists for part {$part->name}. Receive stock before consuming."
            );
        }

        return $item;
    }

    private function resolveBatteryInventoryItem(Battery $battery, ?int $binId, int $workshopId): BatteryInventoryItem
    {
        $query = BatteryInventoryItem::query()
            ->withoutGlobalScope(WorkshopScope::class)
            ->where('workshop_id', $workshopId)
            ->where('battery_id', $battery->getKey());

        if ($binId !== null) {
            $item = (clone $query)->where('bin_id', $binId)->orderByDesc('id')->first();
            if ($item !== null) {
                return $item;
            }
        }

        if ($battery->bin_location_id !== null) {
            $item = (clone $query)->where('bin_id', $battery->bin_location_id)->orderByDesc('id')->first();
            if ($item !== null) {
                return $item;
            }
        }

        $item = $query->orderByDesc('id')->first();
        if ($item === null) {
            throw new DomainException(
                "No inventory bucket exists for battery {$battery->name}. Receive stock before consuming."
            );
        }

        return $item;
    }

    private function resolveLubricantInventoryItem(Lubricant $lubricant, ?int $binId, int $workshopId): LubricantInventoryItem
    {
        $query = LubricantInventoryItem::query()
            ->withoutGlobalScope(WorkshopScope::class)
            ->where('workshop_id', $workshopId)
            ->where('lubricant_id', $lubricant->getKey());

        if ($binId !== null) {
            $item = (clone $query)->where('bin_id', $binId)->orderByDesc('id')->first();
            if ($item !== null) {
                return $item;
            }
        }

        if ($lubricant->bin_location_id !== null) {
            $item = (clone $query)->where('bin_id', $lubricant->bin_location_id)->orderByDesc('id')->first();
            if ($item !== null) {
                return $item;
            }
        }

        $item = $query->orderByDesc('id')->first();
        if ($item === null) {
            throw new DomainException(
                "No inventory bucket exists for lubricant {$lubricant->name}. Receive stock before consuming."
            );
        }

        return $item;
    }

    private function createAssignmentRow(
        EquipmentConsumable $consumable,
        EquipmentConsumableType $type,
        EquipmentConsumableStatus $status,
        float $quantity,
        CarbonImmutable $at,
        ?User $actor,
        ?int $binId,
        ?int $unitId,
        ?float $unitCost,
        ?string $notes,
        ?int $previousAssignmentId = null,
        ?string $stockMovementType = null,
        ?int $stockMovementId = null,
    ): EquipmentConsumableAssignment {
        $totalCost = $unitCost !== null ? round($quantity * $unitCost, 4) : null;

        return EquipmentConsumableAssignment::withoutWorkshopScope(
            fn () => EquipmentConsumableAssignment::create([
                'workshop_id' => $consumable->workshop_id,
                'equipment_consumable_id' => $consumable->getKey(),
                'type' => $type,
                'status' => $status,
                'quantity' => $quantity,
                'unit_id' => $unitId,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'performed_by' => $actor?->getKey(),
                'performed_at' => $at,
                'previous_assignment_id' => $previousAssignmentId,
                'bin_id' => $binId,
                'stock_movement_type' => $stockMovementType,
                'stock_movement_id' => $stockMovementId,
                'notes' => $notes,
            ])
        );
    }

    /**
     * Resolve the polymorphic resource for a consumable without going
     * through the workshop-scoped relation (which would return null when
     * the caller's workshop_id doesn't match the consumable's).
     */
    private function resolveResource(EquipmentConsumable $consumable): Model
    {
        $class = $consumable->resource_type;
        if ($class === null || ! class_exists($class)) {
            throw new DomainException(
                'Consumable references an unknown resource type: '.($consumable->resource_type ?? 'null')
            );
        }

        $resource = $class::withoutWorkshopScope(
            fn () => $class::query()->find($consumable->resource_id)
        );

        if ($resource === null) {
            throw new DomainException(
                'Resource #'.$consumable->resource_id.' ('.$class.') was not found.'
            );
        }

        return $resource;
    }

    /**
     * Resolve the equipment for a consumable without going through the
     * workshop-scoped relation.
     */
    private function resolveEquipment(EquipmentConsumable $consumable): Equipment
    {
        $equipment = Equipment::withoutWorkshopScope(
            fn () => Equipment::query()->find($consumable->equipment_id)
        );

        if ($equipment === null) {
            throw new DomainException(
                'Equipment #'.$consumable->equipment_id.' was not found.'
            );
        }

        return $equipment;
    }

    private function assertSupportedResource(Model $resource): void
    {
        if (! in_array($resource::class, EquipmentConsumable::allowedResourceTypes(), true)) {
            throw new DomainException(
                'Unsupported resource type: '.$resource::class
                .'. Allowed: '.implode(', ', EquipmentConsumable::allowedResourceTypes())
            );
        }
    }

    private function assertOpen(EquipmentConsumable $consumable): void
    {
        // The currentAssignment relation on the consumable model picks up
        // the global WorkshopScope from the related Assignment model. Since
        // we've already authorised the operation by the time we reach this
        // helper, look up the row directly without the scope so we see
        // the assignment regardless of the authenticated user's workshop.
        $current = EquipmentConsumableAssignment::withoutWorkshopScope(
            fn () => $consumable->currentAssignment()->first()
        );
        if ($current === null) {
            throw new DomainException(
                'This consumable is already closed. Record a new assignment first.'
            );
        }
    }

    private function ensureActor(?User $actor): User
    {
        if ($actor === null) {
            throw new DomainException('An actor (user) is required to record a stock movement.');
        }

        return $actor;
    }
}
