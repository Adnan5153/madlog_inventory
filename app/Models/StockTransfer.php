<?php

namespace App\Models;

use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An inter-bin stock transfer. Lifecycle:
 *   draft → in_transit → received   (or cancelled).
 *
 * Each transfer atomically decrements the source bin's bucket and
 * increments the destination bin's bucket on receipt.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $transfer_number
 * @property string $status
 * @property int|null $source_bin_id
 * @property int $destination_bin_id
 * @property int $transferred_by
 * @property int|null $received_by
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $received_at
 */
#[Fillable([
    'workshop_id',
    'transfer_number',
    'status',
    'source_bin_id',
    'destination_bin_id',
    'transferred_by',
    'received_by',
    'dispatched_at',
    'received_at',
    'notes',
])]
class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function sourceBin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'source_bin_id');
    }

    public function destinationBin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'destination_bin_id');
    }

    public function transferer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}