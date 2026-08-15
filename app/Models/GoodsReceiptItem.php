<?php

namespace App\Models;

use Database\Factories\GoodsReceiptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A line on a goods receipt note. Tracks what was actually received against
 * what was originally ordered on the parent PO line.
 *
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $purchase_order_item_id
 * @property int $part_id
 * @property int|null $bin_location_id
 * @property numeric $quantity_ordered
 * @property numeric $quantity_received
 * @property numeric $damaged_quantity
 * @property string|null $batch_number
 * @property Carbon|null $expires_at
 * @property numeric $unit_cost
 * @property string|null $notes
 */
#[Fillable([
    'goods_receipt_id',
    'purchase_order_item_id',
    'part_id',
    'bin_location_id',
    'quantity_ordered',
    'quantity_received',
    'damaged_quantity',
    'batch_number',
    'expires_at',
    'unit_cost',
    'notes',
])]
class GoodsReceiptItem extends Model
{
    /** @use HasFactory<GoodsReceiptItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:2',
            'quantity_received' => 'decimal:2',
            'damaged_quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'expires_at' => 'date',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_location_id');
    }
}
