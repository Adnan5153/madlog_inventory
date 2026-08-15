<?php

namespace App\Models;

use Database\Factories\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A stock adjustment request: a set of signed-quantity changes against
 * specific InventoryItem buckets. Lifecycle:
 *   draft → pending → approved → applied   (or rejected at any time).
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $adjustment_number
 * @property string $status
 * @property string $reason
 * @property string|null $notes
 * @property int $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $applied_at
 */
#[Fillable([
    'workshop_id',
    'adjustment_number',
    'status',
    'reason',
    'notes',
    'requested_by',
    'approved_by',
    'approved_at',
    'applied_at',
])]
class StockAdjustment extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_APPLIED = 'applied';

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }
}
