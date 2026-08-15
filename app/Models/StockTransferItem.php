<?php

namespace App\Models;

use Database\Factories\StockTransferItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line on a StockTransfer. Tracks the part (and optional batch)
 * being moved and the quantity.
 *
 * @property int $id
 * @property int $stock_transfer_id
 * @property int $part_id
 * @property string|null $batch_number
 * @property numeric $quantity
 */
#[Fillable([
    'stock_transfer_id',
    'part_id',
    'batch_number',
    'quantity',
])]
class StockTransferItem extends Model
{
    /** @use HasFactory<StockTransferItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
