<?php

namespace App\Models;

use App\Enums\EquipmentConsumableStatus;
use App\Enums\EquipmentConsumableType;
use Carbon\CarbonImmutable;
use Database\Factories\EquipmentConsumableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One consumable resource (Part | Battery | Lubricant) tracked against
 * one piece of equipment. The lifecycle (assign / install / consume /
 * replace / remove) is recorded as rows on `equipment_consumable_assignments`.
 *
 * Soft-deletable so accidental deletions are recoverable; the underlying
 * assignment rows are kept (no soft-delete on them) so the audit trail
 * survives.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $equipment_id
 * @property string $resource_type
 * @property int $resource_id
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $expected_replacement_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read Equipment $equipment
 * @property-read Model $resource
 * @property-read Collection<int, EquipmentConsumableAssignment> $assignments
 * @property-read EquipmentConsumableAssignment|null $latestAssignment
 * @property-read EquipmentConsumableAssignment|null $currentAssignment
 */
#[Fillable([
    'workshop_id',
    'equipment_id',
    'resource_type',
    'resource_id',
    'assigned_at',
    'expected_replacement_at',
    'notes',
    'created_by',
    'updated_by',
])]
class EquipmentConsumable extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<EquipmentConsumableFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'expected_replacement_at' => 'immutable_date',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Polymorphic resource — Part | Battery | Lubricant.
     */
    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EquipmentConsumableAssignment::class)
            ->orderByDesc('performed_at')
            ->orderByDesc('id');
    }

    /**
     * The most recent assignment row, regardless of status.
     */
    public function latestAssignment(): HasOne
    {
        return $this->hasOne(EquipmentConsumableAssignment::class)
            ->orderByDesc('performed_at')
            ->orderByDesc('id');
    }

    /**
     * The currently open assignment — the one that is still the active
     * operational record for this consumable. Null when the consumable
     * has been fully consumed / replaced / removed / cancelled.
     *
     * Semantics: an assignment is "open" while its status is `Assigned`
     * or `Installed`. If a later assignment with a terminal status
     * (`Consumed`, `Removed`, `Cancelled`) has been written, this
     * relation must return null even if the open row still exists in the
     * ledger — the consumable is logically closed once a terminal event
     * is recorded.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(EquipmentConsumableAssignment::class)
            ->whereIn('status', [
                EquipmentConsumableStatus::Assigned->value,
                EquipmentConsumableStatus::Installed->value,
            ])
            ->whereNotExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('equipment_consumable_assignments as later')
                    ->whereColumn('later.equipment_consumable_id', 'equipment_consumable_assignments.equipment_consumable_id')
                    ->whereColumn('later.id', '>', 'equipment_consumable_assignments.id')
                    ->whereIn('later.status', [
                        EquipmentConsumableStatus::Consumed->value,
                        EquipmentConsumableStatus::Removed->value,
                        EquipmentConsumableStatus::Cancelled->value,
                    ]);
            })
            ->orderByDesc('performed_at')
            ->orderByDesc('id');
    }

    /**
     * Sum of total_cost across all non-cancelled assignments.
     */
    public function totalCost(): float
    {
        return (float) $this->assignments()
            ->where('status', '!=', EquipmentConsumableStatus::Cancelled->value)
            ->sum('total_cost');
    }

    /**
     * Sum of consumed quantity across all consumed assignments.
     */
    public function totalConsumed(): float
    {
        return (float) $this->assignments()
            ->where('type', EquipmentConsumableType::Consumed->value)
            ->sum('quantity');
    }

    /**
     * Whether the expected replacement date is within the next 30 days
     * or already past.
     */
    public function isReplacementDue(): bool
    {
        if ($this->expected_replacement_at === null) {
            return false;
        }

        return $this->expected_replacement_at->startOfDay()
            ->lte(now()->startOfDay()->addDays(30));
    }

    public function isReplacementOverdue(): bool
    {
        if ($this->expected_replacement_at === null) {
            return false;
        }

        return $this->expected_replacement_at->startOfDay()
            ->lt(now()->startOfDay());
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereHas('currentAssignment');
    }

    public function scopeDueForReplacement(Builder $q, int $withinDays = 30): Builder
    {
        return $q->whereNotNull('expected_replacement_at')
            ->whereDate('expected_replacement_at', '<=', now()->addDays($withinDays));
    }

    /**
     * The allowed resource_type values. Centralized here so the form
     * pickers and the service agree on the polymorphic contract.
     *
     * @return list<string>
     */
    public static function allowedResourceTypes(): array
    {
        return [
            Part::class,
            Battery::class,
            Lubricant::class,
        ];
    }

    /**
     * Map a resource_type to a Bootstrap icon. Used by the
     * resource-type-picker component.
     */
    public static function resourceIcon(string $resourceType): string
    {
        return match ($resourceType) {
            Battery::class => 'bi-battery-charging',
            Lubricant::class => 'bi-droplet-fill',
            default => 'bi-nut',
        };
    }

    /**
     * Map a resource_type to a short label.
     */
    public static function resourceLabel(string $resourceType): string
    {
        return match ($resourceType) {
            Battery::class => 'Battery',
            Lubricant::class => 'Lubricant',
            default => 'Part',
        };
    }
}
