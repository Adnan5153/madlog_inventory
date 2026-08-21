<?php

namespace App\Enums;

/**
 * Current state of an `equipment_consumable_assignments` row. Distinct
 * from `EquipmentConsumableType` (the verb that produced it). The status
 * reflects whether the assignment is still the "open" record for its
 * consumable or has been closed out (consumed / replaced / removed /
 * cancelled).
 *
 *   Assigned   — open, resource is tracked against the equipment.
 *   Installed  — open, resource is physically mounted/in service.
 *   Consumed   — closed, the resource was used up; writes an Issue ledger.
 *   Removed    — closed, the resource was taken off; may return to stock.
 *   Cancelled  — closed, the assignment was voided (replaces soft-delete).
 */
enum EquipmentConsumableStatus: string
{
    case Assigned = 'assigned';
    case Installed = 'installed';
    case Consumed = 'consumed';
    case Removed = 'removed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Installed => 'Installed',
            self::Consumed => 'Consumed',
            self::Removed => 'Removed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Assigned => 'info',
            self::Installed => 'primary',
            self::Consumed => 'warning',
            self::Removed => 'danger',
            self::Cancelled => 'secondary',
        };
    }

    /**
     * Whether this status represents an "open" assignment — one that is
     * still the current operational record for its consumable.
     */
    public function isOpen(): bool
    {
        return match ($this) {
            self::Assigned, self::Installed => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
