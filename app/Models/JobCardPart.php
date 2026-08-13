<?php

namespace App\Models;

use Database\Factories\JobCardPartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part attached to a job card. Status flow:
 *   reserved → consumed | returned | partial
 *
 * Inventory is reserved when the row is created, consumed when status flips
 * to consumed, and returned to stock when status flips to returned. The
 * service layer is responsible for posting stock_movements entries.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $job_card_id
 * @property int $part_id
 * @property int|null $inventory_item_id
 * @property int|null $issued_by
 * @property numeric $quantity
 * @property numeric $quantity_consumed
 * @property numeric $quantity_returned
 * @property numeric $unit_price
 * @property string $status
 * @property string|null $notes
 */
#[Fillable([
    'workshop_id',
    'job_card_id',
    'part_id',
    'inventory_item_id',
    'issued_by',
    'quantity',
    'quantity_consumed',
    'quantity_returned',
    'unit_price',
    'status',
    'notes',
])]
class JobCardPart extends Model
{
    /** @use HasFactory<JobCardPartFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_PARTIAL = 'partial';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'quantity_consumed' => 'decimal:2',
            'quantity_returned' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}