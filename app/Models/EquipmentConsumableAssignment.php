<?php

namespace App\Models;

use App\Enums\EquipmentConsumableStatus;
use App\Enums\EquipmentConsumableType;
use Carbon\CarbonImmutable;
use Database\Factories\EquipmentConsumableAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only lifecycle event log for an equipment consumable.
 *
 * Each row records a single verb (assigned / installed / consumed /
 * replaced / removed) for one `equipment_consumable`. Consumed events
 * and remove-with-return events also reference the matching stock
 * movement ledger row polymorphically via `stock_movement_type` +
 * `stock_movement_id`.
 *
 * Corrections are recorded as new rows with `status = 'cancelled'`.
 * No soft deletes — the audit trail must survive.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $equipment_consumable_id
 * @property string $type
 * @property string $status
 * @property numeric $quantity
 * @property int|null $unit_id
 * @property numeric|null $unit_cost
 * @property numeric|null $total_cost
 * @property int|null $performed_by
 * @property CarbonImmutable $performed_at
 * @property int|null $previous_assignment_id
 * @property int|null $bin_id
 * @property string|null $stock_movement_type
 * @property int|null $stock_movement_id
 * @property string|null $notes
 */
#[Fillable([
    'workshop_id',
    'equipment_consumable_id',
    'type',
    'status',
    'quantity',
    'unit_id',
    'unit_cost',
    'total_cost',
    'performed_by',
    'performed_at',
    'previous_assignment_id',
    'bin_id',
    'stock_movement_type',
    'stock_movement_id',
    'notes',
])]
class EquipmentConsumableAssignment extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<EquipmentConsumableAssignmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'performed_at' => 'immutable_datetime',
            'type' => EquipmentConsumableType::class,
            'status' => EquipmentConsumableStatus::class,
        ];
    }

    public function equipmentConsumable(): BelongsTo
    {
        return $this->belongsTo(EquipmentConsumable::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function previousAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_assignment_id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Polymorphic reference to the stock_movement ledger row. The
     * `stock_movement_type` discriminator is 'part' | 'battery' |
     * 'lubricant' — the actual class lives on the corresponding
     * StockMovement / BatteryStockMovement / LubricantStockMovement
     * row, but we resolve through the discriminator to keep the
     * foreign key flexible.
     */
    public function stockMovement(): MorphTo
    {
        return $this->morphTo('stock_movement', 'stock_movement_type', 'stock_movement_id');
    }

    /**
     * Resolve the stock movement row referenced by stock_movement_type /
     * stock_movement_id into the concrete model. Differs from the
     * default morphTo because the column name is a discriminator, not
     * a fully-qualified class.
     */
    public function stockMovementRecord(): ?Model
    {
        if ($this->stock_movement_type === null || $this->stock_movement_id === null) {
            return null;
        }

        return match ($this->stock_movement_type) {
            'part' => StockMovement::find($this->stock_movement_id),
            'battery' => BatteryStockMovement::find($this->stock_movement_id),
            'lubricant' => LubricantStockMovement::find($this->stock_movement_id),
            default => null,
        };
    }
}
