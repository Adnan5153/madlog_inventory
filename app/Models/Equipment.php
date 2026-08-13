<?php

namespace App\Models;

use Database\Factories\EquipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Physical equipment maintained in a workshop (asset register).
 * Tracks warranty dates and links to a department + bin location.
 * Workshop-scoped.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int|null $department_id
 * @property int|null $bin_location_id
 * @property string $name
 * @property string|null $asset_number
 * @property string|null $equipment_type
 * @property string|null $manufacturer
 * @property string|null $model
 * @property string|null $serial_number
 * @property \Illuminate\Support\Carbon|null $purchase_date
 * @property \Illuminate\Support\Carbon|null $warranty_expires_at
 * @property string $status
 * @property string|null $notes
 * @property bool $is_active
 */
#[Fillable([
    'workshop_id',
    'department_id',
    'bin_location_id',
    'name',
    'asset_number',
    'equipment_type',
    'manufacturer',
    'model',
    'serial_number',
    'purchase_date',
    'warranty_expires_at',
    'status',
    'notes',
    'is_active',
])]
class Equipment extends Model
{
    /** @use HasFactory<EquipmentFactory> */
    use HasFactory, Concerns\BelongsToWorkshop;

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whether the warranty expires within the next 30 days.
     */
    public function warrantyExpiringSoon(int $days = 30): bool
    {
        if (! $this->warranty_expires_at) {
            return false;
        }

        return $this->warranty_expires_at->between(now(), now()->addDays($days));
    }

    public const STATUS_ACTIVE = 'active';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_RETIRED = 'retired';
    public const STATUS_DISPOSED = 'disposed';
}