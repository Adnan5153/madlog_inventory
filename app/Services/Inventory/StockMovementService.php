<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Stock-movement ledger writer. Every change to an InventoryItem.quantity
 * must flow through this service so the ledger stays consistent.
 *
 * StockMovement rows are append-only (enforced on the model). Corrections
 * are made by posting a reversing movement of the same type with the
 * opposite sign on quantity.
 */
class StockMovementService
{
    /**
     * Append a stock_movement row and update the matching InventoryItem
     * quantity atomically.
     *
     * @throws DomainException when the resulting on-hand would go below
     *                        zero and `inventory.allow_negative_stock` is false.
     */
    public function record(
        StockMovementType $type,
        InventoryItem $item,
        float $quantity,
        User $actor,
        ?float $unitCost = null,
        ?string $reason = null,
        mixed $reference = null,
    ): StockMovement {
        if ($quantity === 0.0) {
            throw new DomainException('Stock movement quantity cannot be zero.');
        }

        return DB::transaction(function () use ($type, $item, $quantity, $actor, $unitCost, $reason, $reference) {
            $allowNegative = (bool) setting('inventory.allow_negative_stock', false, $item->workshop_id);
            $newQty = (float) $item->quantity + $quantity;
            if (! $allowNegative && $newQty < 0) {
                throw new DomainException(sprintf(
                    'Adjustment would drop on-hand below zero (current %.2f, change %.2f). Enable inventory.allow_negative_stock to allow.',
                    (float) $item->quantity,
                    $quantity,
                ));
            }

            $item->quantity = $newQty;
            $item->save();

            return StockMovement::create([
                'workshop_id' => $item->workshop_id,
                'part_id' => $item->part_id,
                'bin_id' => $item->bin_id,
                'user_id' => $actor?->getKey(),
                'inventory_item_id' => $item->id,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference?->getKey(),
                'reason' => $reason,
                'occurred_at' => now(),
            ]);
        });
    }
}