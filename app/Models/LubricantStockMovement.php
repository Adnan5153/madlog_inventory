<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\LubricantStockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only ledger row for lubricant stock. Mirror of StockMovement
 * scoped to lubricants. Boot hooks below prevent update/delete — use a
 * reversing movement of the same type to correct a prior entry.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $lubricant_id
 * @property int|null $bin_id
 * @property int|null $user_id
 * @property int|null $lubricant_inventory_item_id
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
    'lubricant_id',
    'bin_id',
    'user_id',
    'lubricant_inventory_item_id',
    'type',
    'quantity',
    'unit_cost',
    'reference_type',
    'reference_id',
    'reason',
    'occurred_at',
])]
class LubricantStockMovement extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<LubricantStockMovementFactory> */
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
                'LubricantStockMovement rows are append-only. Post a reversing movement instead.'
            );
        });

        static::deleting(function (): bool {
            throw new LogicException(
                'LubricantStockMovement rows cannot be deleted. Post a reversing movement instead.'
            );
        });
    }

    public function lubricant(): BelongsTo
    {
        return $this->belongsTo(Lubricant::class);
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lubricantInventoryItem(): BelongsTo
    {
        return $this->belongsTo(LubricantInventoryItem::class, 'lubricant_inventory_item_id');
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
