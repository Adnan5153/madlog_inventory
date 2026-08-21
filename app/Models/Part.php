<?php

namespace App\Models;

use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workshop_id
 * @property int|null $category_id
 * @property int|null $unit_id
 * @property string|null $sku
 * @property string|null $oem_part_number
 * @property string|null $brand
 * @property string|null $barcode
 * @property string $name
 * @property string|null $description
 * @property string|null $equipment_compatibility
 * @property int|null $bin_location_id
 * @property string|null $location
 * @property int|null $supplier_id
 * @property int $reorder_threshold
 * @property int $reorder_quantity
 * @property string $cost_price
 * @property bool $is_active
 */
#[Fillable([
    'workshop_id',
    'category_id',
    'unit_id',
    'sku',
    'oem_part_number',
    'brand',
    'barcode',
    'name',
    'description',
    'equipment_compatibility',
    'bin_location_id',
    'location',
    'supplier_id',
    'reorder_threshold',
    'reorder_quantity',
    'cost_price',
    'is_active',
])]
class Part extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<PartFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reorder_threshold' => 'integer',
            'reorder_quantity' => 'integer',
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_location_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function jobCardParts(): HasMany
    {
        return $this->hasMany(JobCardPart::class);
    }

    /**
     * Whether this part needs reordering based on aggregated stock across
     * all its inventory_items (bin+batch buckets).
     */
    public function needsReorder(): bool
    {
        $total = (float) $this->inventoryItems()->sum('quantity');

        return $total <= (float) $this->reorder_threshold;
    }

    /**
     * Current on-hand quantity summed across every bin/batch bucket.
     */
    public function onHandQuantity(): float
    {
        return (float) $this->inventoryItems()->sum('quantity');
    }
}
