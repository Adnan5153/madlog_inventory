<?php

namespace App\Models;

use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchase order to a supplier. Status drives workflow:
 *   draft → submitted → approved → partially_received | received → cancelled
 *
 * @property int $id
 * @property string $po_number
 * @property int $workshop_id
 * @property int $supplier_id
 * @property int $created_by
 * @property int|null $approved_by
 * @property string $status
 * @property \Illuminate\Support\Carbon $order_date
 * @property \Illuminate\Support\Carbon|null $expected_date
 * @property \Illuminate\Support\Carbon|null $received_date
 * @property numeric $subtotal
 * @property numeric $tax
 * @property numeric $total
 * @property string|null $notes
 */
#[Fillable([
    'po_number',
    'workshop_id',
    'supplier_id',
    'created_by',
    'approved_by',
    'status',
    'order_date',
    'expected_date',
    'received_date',
    'subtotal',
    'tax',
    'total',
    'notes',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'received_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /** Status constants — the migration column is a string for forward-compat. */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
        ], true);
    }

    public function isFullyReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Recompute status based on items' received quantities. Call this after
     * a receipt to keep the parent status in sync.
     */
    public function refreshReceiptStatus(): void
    {
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return;
        }

        $allReceived = $this->items->every(
            fn (PurchaseOrderItem $item) => (float) $item->quantity_received >= (float) $item->quantity_ordered
        );
        $anyReceived = $this->items->contains(
            fn (PurchaseOrderItem $item) => (float) $item->quantity_received > 0
        );

        if ($allReceived) {
            $this->status = self::STATUS_RECEIVED;
            $this->received_date = now();
        } elseif ($anyReceived) {
            $this->status = self::STATUS_PARTIALLY_RECEIVED;
        }

        $this->save();
    }
}