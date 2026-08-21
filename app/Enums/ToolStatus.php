<?php

namespace App\Enums;

/**
 * Operational lifecycle status of a tool. Distinct from `ToolCondition`:
 * a tool may be `Good` in condition but `CheckedOut` or `UnderMaintenance`
 * in status. Drives the row-template badge and the status filter.
 */
enum ToolStatus: string
{
    case Available = 'available';
    case CheckedOut = 'checked_out';
    case UnderMaintenance = 'under_maintenance';
    case OutOfService = 'out_of_service';
    case Lost = 'lost';
    case Damaged = 'damaged';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::CheckedOut => 'Checked out',
            self::UnderMaintenance => 'Under maintenance',
            self::OutOfService => 'Out of service',
            self::Lost => 'Lost',
            self::Damaged => 'Damaged',
            self::Retired => 'Retired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::CheckedOut => 'primary',
            self::UnderMaintenance => 'warning',
            self::OutOfService => 'secondary',
            self::Lost, self::Damaged => 'danger',
            self::Retired => 'secondary',
        };
    }

    public function isCheckoutable(): bool
    {
        return $this === self::Available;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
