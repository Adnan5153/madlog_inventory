<?php

namespace App\Models;

use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A production/lot batch of a part. InventoryItems may reference a Batch
 * via the optional `batch_number` string field; this Batch row is the
 * authoritative metadata (manufactured_at, expires_at, lifecycle status).
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $part_id
 * @property string $batch_number
 * @property Carbon|null $manufactured_at
 * @property Carbon|null $expires_at
 * @property numeric $initial_quantity
 * @property numeric $current_quantity
 * @property string $status
 */
#[Fillable([
    'workshop_id',
    'part_id',
    'batch_number',
    'manufactured_at',
    'expires_at',
    'initial_quantity',
    'current_quantity',
    'status',
])]
class Batch extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DEPLETED = 'depleted';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_RECALLED = 'recalled';

    protected function casts(): array
    {
        return [
            'manufactured_at' => 'date',
            'expires_at' => 'date',
            'initial_quantity' => 'decimal:2',
            'current_quantity' => 'decimal:2',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeExpiringSoon(Builder $q, int $days = 30): Builder
    {
        return $q->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>=', now()->toDateString());
    }

    public function scopeExpired(Builder $q): Builder
    {
        return $q->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->toDateString());
    }

    public function remainingQuantity(): float
    {
        return (float) $this->current_quantity;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
