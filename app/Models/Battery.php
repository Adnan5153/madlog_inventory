<?php

namespace App\Models;

use App\Enums\StockStatus;
use Database\Factories\BatteryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Battery SKU — a sellable/trackable inventory unit distinct from generic
 * Parts. Workshop-scoped; each battery belongs to exactly one workshop.
 *
 * Stock lives on `battery_inventory_items` (per-bin/per-batch bucket).
 * The aggregate `on_hand` is the sum across all buckets for the SKU.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $battery_code
 * @property string|null $sku
 * @property string $name
 * @property string|null $barcode
 * @property string|null $brand
 * @property string|null $manufacturer_part_number
 * @property string|null $description
 * @property string $battery_type
 * @property string|null $application_type
 * @property string $condition
 * @property string $status
 * @property string|null $voltage
 * @property string|null $capacity_ah
 * @property int|null $cold_cranking_amps
 * @property int|null $reserve_capacity
 * @property string|null $terminal_type
 * @property string|null $length_mm
 * @property string|null $width_mm
 * @property string|null $height_mm
 * @property string|null $weight_kg
 * @property string|null $polarity
 * @property string $cost_price
 * @property int|null $supplier_id
 * @property int|null $bin_location_id
 * @property int $reorder_threshold
 * @property int $reorder_quantity
 * @property int|null $warranty_period_months
 * @property Carbon|null $warranty_expiry
 * @property bool $is_active
 * @property string|null $notes
 */
#[Fillable([
    'workshop_id',
    'battery_code',
    'sku',
    'name',
    'barcode',
    'brand',
    'manufacturer_part_number',
    'description',
    'battery_type',
    'application_type',
    'condition',
    'status',
    'voltage',
    'capacity_ah',
    'cold_cranking_amps',
    'reserve_capacity',
    'terminal_type',
    'length_mm',
    'width_mm',
    'height_mm',
    'weight_kg',
    'polarity',
    'cost_price',
    'supplier_id',
    'bin_location_id',
    'reorder_threshold',
    'reorder_quantity',
    'warranty_period_months',
    'warranty_expiry',
    'is_active',
    'notes',
])]
class Battery extends Model
{
    use Concerns\BelongsToWorkshop;
    /** @use HasFactory<BatteryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'voltage' => 'decimal:2',
            'capacity_ah' => 'decimal:2',
            'length_mm' => 'decimal:2',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'weight_kg' => 'decimal:3',
            'cost_price' => 'decimal:2',
            'cold_cranking_amps' => 'integer',
            'reserve_capacity' => 'integer',
            'reorder_threshold' => 'integer',
            'reorder_quantity' => 'integer',
            'warranty_period_months' => 'integer',
            'warranty_expiry' => 'date',
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

    public function batteryInventoryItems(): HasMany
    {
        return $this->hasMany(BatteryInventoryItem::class, 'battery_id');
    }

    public function batteryStockMovements(): HasMany
    {
        return $this->hasMany(BatteryStockMovement::class, 'battery_id');
    }

    public function batteryStockAdjustmentItems(): HasMany
    {
        return $this->hasMany(BatteryStockAdjustmentItem::class, 'battery_id');
    }

    /**
     * Current on-hand quantity summed across all bin/batch buckets.
     * Prefers the eager-loaded `on_hand` attribute when present (added
     * by `withSum('batteryInventoryItems as on_hand', 'quantity')`),
     * so the show / index path doesn't round-trip per row.
     */
    public function onHandQuantity(): float
    {
        if (array_key_exists('on_hand', $this->attributes)) {
            return (float) $this->attributes['on_hand'];
        }

        return (float) $this->batteryInventoryItems()->sum('quantity');
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
        return $q->where('batteries.is_active', true);
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM battery_inventory_items WHERE battery_inventory_items.battery_id = batteries.id) > batteries.reorder_threshold'
        );
    }

    public function scopeLowStock(Builder $q): Builder
    {
        return $q->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM battery_inventory_items WHERE battery_inventory_items.battery_id = batteries.id) > 0'
        )->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM battery_inventory_items WHERE battery_inventory_items.battery_id = batteries.id) <= batteries.reorder_threshold'
        );
    }

    public function scopeOutOfStock(Builder $q): Builder
    {
        return $q->whereRaw(
            '(SELECT COALESCE(SUM(quantity),0) FROM battery_inventory_items WHERE battery_inventory_items.battery_id = batteries.id) <= 0'
        );
    }
}
