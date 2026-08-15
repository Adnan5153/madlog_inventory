<?php

namespace App\Models;

use Database\Factories\BinLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical location where parts are stored. Each bin has a human-friendly
 * code (e.g. A-12) and optional zone/aisle/shelf labels for layout views.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $code
 * @property string|null $zone
 * @property string|null $aisle
 * @property string|null $shelf
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable([
    'workshop_id',
    'code',
    'zone',
    'aisle',
    'shelf',
    'description',
    'is_active',
])]
class BinLocation extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<BinLocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'bin_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'bin_id');
    }

    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class, 'bin_id');
    }

    /**
     * Total quantity held in this bin across all parts.
     */
    public function totalQuantity(): float
    {
        return (float) $this->inventoryItems()->sum('quantity');
    }
}
