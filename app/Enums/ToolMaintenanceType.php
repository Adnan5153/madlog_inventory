<?php

namespace App\Enums;

/**
 * Type of maintenance performed on a tool. Drives the maintenance
 * record form's dropdown and the row template badge.
 */
enum ToolMaintenanceType: string
{
    case Preventive = 'preventive';
    case Repair = 'repair';
    case Inspection = 'inspection';
    case Cleaning = 'cleaning';
    case Replacement = 'replacement';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Preventive => 'Preventive',
            self::Repair => 'Repair',
            self::Inspection => 'Inspection',
            self::Cleaning => 'Cleaning',
            self::Replacement => 'Replacement',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Preventive => 'info',
            self::Repair => 'warning',
            self::Inspection => 'primary',
            self::Cleaning => 'secondary',
            self::Replacement => 'info',
            self::Other => 'secondary',
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
