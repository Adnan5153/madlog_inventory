<?php

namespace App\Models;

use Database\Factories\JobCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A job card ties parts, a vehicle, and a mechanic together for a single
 * repair. Parts flow through JobCardPart (reserved/consumed/returned).
 *
 * @property int $id
 * @property string $job_number
 * @property int $workshop_id
 * @property int|null $mechanic_id
 * @property int $created_by
 * @property string|null $vehicle_make
 * @property string|null $vehicle_model
 * @property string|null $vehicle_plate
 * @property string|null $vehicle_vin
 * @property string $status
 * @property string|null $description
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 */
#[Fillable([
    'job_number',
    'workshop_id',
    'mechanic_id',
    'created_by',
    'vehicle_make',
    'vehicle_model',
    'vehicle_plate',
    'vehicle_vin',
    'status',
    'description',
    'opened_at',
    'closed_at',
])]
class JobCard extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<JobCardFactory> */
    use HasFactory;

    /** Status constants — same string set as the migration. */
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(JobCardPart::class);
    }

    public function stockMovements()
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Sum of unit_price * quantity_consumed across all parts attached.
     */
    public function totalPartsCost(): float
    {
        return (float) $this->parts()
            ->selectRaw('SUM(unit_price * quantity_consumed) as total')
            ->value('total');
    }
}
