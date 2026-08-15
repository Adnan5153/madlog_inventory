<?php

namespace App\Models;

use Database\Factories\SerialNumberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An individual serial-numbered unit of a part. Each SerialNumber belongs
 * to a workshop and a part; the (workshop_id, part_id, serial) tuple is
 * unique to enforce per-part uniqueness of serial labels.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $part_id
 * @property int|null $inventory_item_id
 * @property string $serial
 * @property string $status
 * @property Carbon|null $purchased_at
 * @property Carbon|null $sold_at
 */
#[Fillable([
    'workshop_id',
    'part_id',
    'inventory_item_id',
    'serial',
    'status',
    'purchased_at',
    'sold_at',
])]
class SerialNumber extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<SerialNumberFactory> */
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_ALLOCATED = 'allocated';

    public const STATUS_SOLD = 'sold';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_SCRAPPED = 'scrapped';

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'sold_at' => 'date',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
