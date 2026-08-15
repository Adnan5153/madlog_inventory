<?php

namespace App\Models;

use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A per-(part, bin, batch) bucket that holds an actual quantity. Stock
 * movements change the quantity on these rows; the stock_movements ledger
 * records the history. A part may have many of these (different bins,
 * different batches).
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $part_id
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
    'part_id',
    'bin_id',
    'supplier_id',
    'batch_number',
    'quantity',
    'reserved_quantity',
    'cost_price',
    'expires_at',
])]
class InventoryItem extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'reserved_quantity' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Quantity available to issue (not reserved for a job card).
     */
    public function availableQuantity(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }
}
