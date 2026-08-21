<?php

namespace App\Models;

use Database\Factories\BatteryInventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Per-(battery, bin, batch) bucket for current on-hand quantity. Mirror
 * of InventoryItem but for batteries.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $battery_id
 * @property int|null $bin_id
 * @property int|null $supplier_id
 * @property string|null $batch_number
 * @property numeric $quantity
 * @property numeric $reserved_quantity
 * @property numeric $cost_price
 * @property Carbon|null $expires_at
 */
#[Fillable([
    'workshop_id',
    'battery_id',
    'bin_id',
    'supplier_id',
    'batch_number',
    'quantity',
    'reserved_quantity',
    'cost_price',
    'expires_at',
])]
class BatteryInventoryItem extends Model
{
    use Concerns\BelongsToWorkshop;
    /** @use HasFactory<BatteryInventoryItemFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reserved_quantity' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    public function battery(): BelongsTo
    {
        return $this->belongsTo(Battery::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batteryStockMovements(): HasMany
    {
        return $this->hasMany(BatteryStockMovement::class, 'battery_inventory_item_id');
    }

    public function availableQuantity(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }
}
