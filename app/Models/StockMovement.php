<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only ledger row. Movements are never updated or deleted; corrections
 * require a reversing movement of the same type with the opposite sign on
 * quantity. Enforced in service code (see InventoryService) and via
 * model event hooks below.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $part_id
 * @property int|null $bin_id
 * @property int|null $user_id
 * @property int|null $inventory_item_id
 * @property string $type
 * @property numeric $quantity
 * @property numeric|null $unit_cost
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $reason
 * @property \Illuminate\Support\Carbon $occurred_at
 */
#[Fillable([
    'workshop_id',
    'part_id',
    'bin_id',
    'user_id',
    'inventory_item_id',
    'type',
    'quantity',
    'unit_cost',
    'reference_type',
    'reference_id',
    'reason',
    'occurred_at',
])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

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
            throw new \LogicException(
                'StockMovement rows are append-only. Post a reversing movement instead.'
            );
        });

        static::deleting(function (): bool {
            throw new \LogicException(
                'StockMovement rows cannot be deleted. Post a reversing movement instead.'
            );
        });
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Polymorphic reference back to the source document (PO, JobCard, etc.).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Sign tells direction. Positive = incoming, negative = outgoing.
     */
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
