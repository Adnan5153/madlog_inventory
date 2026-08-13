<?php

namespace App\Models;

use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a purchase order. Tracks ordered vs received quantities so a
 * single PO can be partially received across multiple deliveries.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $part_id
 * @property numeric $quantity_ordered
 * @property numeric $quantity_received
 * @property numeric $unit_cost
 * @property numeric $line_total
 */
#[Fillable([
    'purchase_order_id',
    'part_id',
    'quantity_ordered',
    'quantity_received',
    'unit_cost',
    'line_total',
])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:2',
            'quantity_received' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function remainingQuantity(): float
    {
        return (float) $this->quantity_ordered - (float) $this->quantity_received;
    }

    public function isFullyReceived(): bool
    {
        return $this->remainingQuantity() <= 0;
    }
}