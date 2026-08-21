<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\BatteryStockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only ledger row for battery stock. Mirror of StockMovement
 * scoped to batteries. Boot hooks below prevent update/delete — use a
 * reversing movement of the same type to correct a prior entry.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $battery_id
 * @property int|null $bin_id
 * @property int|null $user_id
 * @property int|null $battery_inventory_item_id
 * @property string $type
 * @property numeric $quantity
 * @property numeric|null $unit_cost
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $reason
 * @property Carbon $occurred_at
 */
#[Fillable([
    'workshop_id',
    'battery_id',
    'bin_id',
    'user_id',
    'battery_inventory_item_id',
    'type',
    'quantity',
    'unit_cost',
    'reference_type',
    'reference_id',
    'reason',
    'occurred_at',
])]
class BatteryStockMovement extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<BatteryStockMovementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'occurred_at' => 'datetime',
            'type' => StockMovementType::class,
        ];
    }

    /**
     * Boot: prevent mutation/deletion. Corrections are reversing movements.
     */
    public static function boot(): void
    {
        parent::boot();

        static::updating(function (): bool {
            throw new LogicException(
                'BatteryStockMovement rows are append-only. Post a reversing movement instead.'
            );
        });

        static::deleting(function (): bool {
            throw new LogicException(
                'BatteryStockMovement rows cannot be deleted. Post a reversing movement instead.'
            );
        });
    }

    public function battery(): BelongsTo
    {
        return $this->belongsTo(Battery::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function batteryInventoryItem(): BelongsTo
    {
        return $this->belongsTo(BatteryInventoryItem::class, 'battery_inventory_item_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isInbound(): bool
    {
        return $this->type instanceof StockMovementType
            ? $this->type->isInbound()
            : false;
    }

    public function isOutbound(): bool
    {
        return $this->type instanceof StockMovementType
            ? $this->type->isOutbound()
            : false;
    }
}
