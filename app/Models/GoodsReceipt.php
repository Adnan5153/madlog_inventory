<?php

namespace App\Models;

use Database\Factories\GoodsReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A goods receipt note (GRN) recorded against a purchase order. Each GRN
 * represents one delivery against the parent PO; a PO can have multiple
 * GRNs (partial deliveries).
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $purchase_order_id
 * @property int|null $bin_location_id
 * @property int $received_by
 * @property string $grn_number
 * @property string|null $supplier_invoice_number
 * @property string $status
 * @property Carbon $received_at
 * @property string|null $notes
 */
#[Fillable([
    'workshop_id',
    'purchase_order_id',
    'bin_location_id',
    'received_by',
    'grn_number',
    'supplier_invoice_number',
    'status',
    'received_at',
    'notes',
])]
class GoodsReceipt extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<GoodsReceiptFactory> */
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_DISPUTED = 'disputed';

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_location_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function isPartial(): bool
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    public function isDisputed(): bool
    {
        return $this->status === self::STATUS_DISPUTED;
    }
}
