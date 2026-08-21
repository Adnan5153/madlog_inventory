<?php

namespace App\Models;

use App\Enums\StockStatus;
use Database\Factories\LubricantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lubricant SKU — a sellable/trackable inventory unit distinct from
 * generic Parts. Workshop-scoped; each lubricant belongs to exactly one
 * workshop.
 *
 * Stock lives on `lubricant_inventory_items` (per-bin/per-batch bucket).
 * The aggregate `on_hand` is the sum across all buckets for the SKU.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $lubricant_code
 * @property string|null $sku
 * @property string $name
 * @property string|null $barcode
 * @property string|null $brand
 * @property string|null $manufacturer
 * @property string|null $manufacturer_part_number
 * @property string|null $description
 * @property string $lubricant_type
 * @property string|null $viscosity_grade
 * @property string|null $application_type
 * @property string $status
 * @property string|null $oem_specification
 * @property string|null $acea_specification
 * @property string|null $api_specification
 * @property string|null $iso_grade
 * @property string|null $nlgi_grade
 * @property string $package_type
 * @property string $package_size
 * @property string $package_unit
 * @property string $cost_price
 * @property int|null $supplier_id
 * @property int|null $bin_location_id
 * @property int $reorder_threshold
 * @property int $reorder_quantity
 * @property bool $is_active
 * @property string|null $notes
 */
#[Fillable([
    'workshop_id',
    'lubricant_code',
    'sku',
    'name',
    'barcode',
    'brand',
    'manufacturer',
    'manufacturer_part_number',
    'description',
    'lubricant_type',
    'viscosity_grade',
    'application_type',
    'status',
    'oem_specification',
    'acea_specification',
    'api_specification',
    'iso_grade',
    'nlgi_grade',
    'package_type',
    'package_size',
    'package_unit',
    'cost_price',
    'supplier_id',
    'bin_location_id',
    'reorder_threshold',
    'reorder_quantity',
    'is_active',
    'notes',
])]
class Lubricant extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<LubricantFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'package_size' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'reorder_threshold' => 'integer',
            'reorder_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_location_id');
    }

    public function lubricantInventoryItems(): HasMany
    {
        return $this->hasMany(LubricantInventoryItem::class, 'lubricant_id');
    }

    public function lubricantStockMovements(): HasMany
    {
        return $this->hasMany(LubricantStockMovement::class, 'lubricant_id');
    }

    public function lubricantStockAdjustmentItems(): HasMany
    {
        return $this->hasMany(LubricantStockAdjustmentItem::class, 'lubricant_id');
    }

    /**
     * Current on-hand quantity summed across all bin/batch buckets.
     * Prefers the eager-loaded `on_hand` attribute when present (added
     * by `withSum('lubricantInventoryItems as on_hand', 'quantity')`),
     * so the show / index path doesn't round-trip per row.
     */
    public function onHandQuantity(): float
    {
        if (array_key_exists('on_hand', $this->attributes)) {
            return (float) $this->attributes['on_hand'];
        }

        return (float) $this->lubricantInventoryItems()->sum('quantity');
    }

    public function needsReorder(): bool
    {
        return $this->onHandQuantity() <= (float) $this->reorder_threshold;
    }

    /**
     * Stock status bucket used by the row template and the stock_status
     * filter. See App\Enums\StockStatus.
     */
    public function stockStatus(): StockStatus
    {
        $onHand = $this->onHandQuantity();

        if ($onHand <= 0.0) {
            return StockStatus::OutOfStock;
        }

        return $onHand <= (float) $this->reorder_threshold
            ? StockStatus::LowStock
            : StockStatus::InStock;
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('lubricants.is_active', true);
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM lubricant_inventory_items WHERE lubricant_inventory_items.lubricant_id = lubricants.id) > lubricants.reorder_threshold'
        );
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM lubricant_inventory_items WHERE lubricant_inventory_items.lubricant_id = lubricants.id) > 0'
        )->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM lubricant_inventory_items WHERE lubricant_inventory_items.lubricant_id = lubricants.id) <= lubricants.reorder_threshold'
        );
    }

    public function scopeOutOfStock(Builder $q): Builder
    {
        return $q->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM lubricant_inventory_items WHERE lubricant_inventory_items.lubricant_id = lubricants.id) <= 0'
        );
    }
}
