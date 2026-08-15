<?php

namespace App\Models;

use Database\Factories\StockAdjustmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on a StockAdjustment, recording the before/after quantity
 * and the signed adjustment delta. The InventoryItem is the parent bucket
 * whose quantity is updated when the adjustment is applied.
 *
 * @property int $id
 * @property int $stock_adjustment_id
 * @property int $inventory_item_id
 * @property numeric $before_quantity
 * @property numeric $adjustment_quantity
 * @property numeric $after_quantity
 * @property numeric|null $unit_cost
 */
#[Fillable([
    'stock_adjustment_id',
    'inventory_item_id',
    'before_quantity',
    'adjustment_quantity',
    'after_quantity',
    'unit_cost',
])]
class StockAdjustmentItem extends Model
{
    /** @use HasFactory<StockAdjustmentItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'before_quantity' => 'decimal:2',
            'adjustment_quantity' => 'decimal:2',
            'after_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
