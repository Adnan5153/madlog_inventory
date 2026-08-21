<?php

namespace App\Enums;

/**
 * Lifecycle verb for an equipment consumable assignment. The same value is
 * used to label both the assignment row (`type`) and the human-facing
 * timeline. The companion `EquipmentConsumableStatus` represents the
 * current open/closed state of that assignment.
 *
 *   assigned  — resource registered to the equipment; no stock change.
 *   installed — physically placed on/in the equipment; no stock change.
 *   consumed  — quantity used up; posts an Issue stock movement.
 *   replaced  — superseded by a new assignment on a fresh consumable.
 *   removed   — taken off the equipment (may return to stock).
 */
enum EquipmentConsumableType: string
{
    case Assigned = 'assigned';
    case Installed = 'installed';
    case Consumed = 'consumed';
    case Replaced = 'replaced';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Assigned => 'Assigned',
            self::Installed => 'Installed',
            self::Consumed => 'Consumed',
            self::Replaced => 'Replaced',
            self::Removed => 'Removed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Assigned => 'info',
            self::Installed => 'primary',
            self::Consumed => 'warning',
            self::Replaced => 'secondary',
            self::Removed => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Assigned => 'bi-link-45deg',
            self::Installed => 'bi-tools',
            self::Consumed => 'bi-droplet',
            self::Replaced => 'bi-arrow-left-right',
            self::Removed => 'bi-x-circle',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
