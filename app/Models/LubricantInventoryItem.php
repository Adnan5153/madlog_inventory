<?php

namespace App\Models;

use Database\Factories\LubricantInventoryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Per-(lubricant, bin, batch) bucket for current on-hand quantity.
 * Mirror of InventoryItem but for lubricants.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $lubricant_id
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
    'lubricant_id',
    'bin_id',
    'supplier_id',
    'batch_number',
    'quantity',
    'reserved_quantity',
    'cost_price',
    'expires_at',
])]
class LubricantInventoryItem extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<LubricantInventoryItemFactory> */
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

    public function lubricant(): BelongsTo
    {
        return $this->belongsTo(Lubricant::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function lubricantStockMovements(): HasMany
    {
        return $this->hasMany(LubricantStockMovement::class, 'lubricant_inventory_item_id');
    }

    public function availableQuantity(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }
}
