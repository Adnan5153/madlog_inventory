<?php

namespace App\Models;

use Database\Factories\LubricantStockAdjustmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a LubricantStockAdjustment, recording the booked quantity,
 * the counted quantity and (optionally) the bin the change applies to.
 *
 * @property int $id
 * @property int $lubricant_stock_adjustment_id
 * @property int $lubricant_id
 * @property int|null $lubricant_inventory_item_id
 * @property int|null $bin_id
 * @property numeric $quantity
 * @property numeric $counted_quantity
 * @property numeric|null $unit_cost
 * @property string|null $reason
 */
#[Fillable([
    'lubricant_stock_adjustment_id',
    'lubricant_id',
    'lubricant_inventory_item_id',
    'bin_id',
    'quantity',
    'counted_quantity',
    'unit_cost',
    'reason',
])]
class LubricantStockAdjustmentItem extends Model
{
    /** @use HasFactory<LubricantStockAdjustmentItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function lubricantStockAdjustment(): BelongsTo
    {
        return $this->belongsTo(LubricantStockAdjustment::class);
    }

    public function lubricant(): BelongsTo
    {
        return $this->belongsTo(Lubricant::class);
    }

    public function lubricantInventoryItem(): BelongsTo
    {
        return $this->belongsTo(LubricantInventoryItem::class, 'lubricant_inventory_item_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }
}
