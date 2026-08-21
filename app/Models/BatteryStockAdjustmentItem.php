<?php

namespace App\Models;

use Database\Factories\BatteryStockAdjustmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a BatteryStockAdjustment, recording the booked quantity, the
 * counted quantity and (optionally) the bin the change applies to.
 *
 * @property int $id
 * @property int $battery_stock_adjustment_id
 * @property int $battery_id
 * @property int|null $battery_inventory_item_id
 * @property int|null $bin_id
 * @property numeric $quantity
 * @property numeric $counted_quantity
 * @property numeric|null $unit_cost
 * @property string|null $reason
 */
#[Fillable([
    'battery_stock_adjustment_id',
    'battery_id',
    'battery_inventory_item_id',
    'bin_id',
    'quantity',
    'counted_quantity',
    'unit_cost',
    'reason',
])]
class BatteryStockAdjustmentItem extends Model
{
    /** @use HasFactory<BatteryStockAdjustmentItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'counted_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function batteryStockAdjustment(): BelongsTo
    {
        return $this->belongsTo(BatteryStockAdjustment::class);
    }

    public function battery(): BelongsTo
    {
        return $this->belongsTo(Battery::class);
    }

    public function batteryInventoryItem(): BelongsTo
    {
        return $this->belongsTo(BatteryInventoryItem::class, 'battery_inventory_item_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }
}
