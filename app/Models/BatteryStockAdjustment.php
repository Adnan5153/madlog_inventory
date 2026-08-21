<?php

namespace App\Models;

use App\Enums\BatteryStockAdjustmentStatus;
use Database\Factories\BatteryStockAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A pending/approved/rejected stock adjustment for batteries. Approval
 * writes one BatteryStockMovement row per item and updates the
 * corresponding BatteryInventoryItem.quantity.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $reference
 * @property string $status
 * @property string $reason
 * @property string|null $notes
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'workshop_id',
    'reference',
    'status',
    'reason',
    'notes',
    'requested_by',
    'approved_by',
    'approved_at',
])]
class BatteryStockAdjustment extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<BatteryStockAdjustmentFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => BatteryStockAdjustmentStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BatteryStockAdjustmentItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
