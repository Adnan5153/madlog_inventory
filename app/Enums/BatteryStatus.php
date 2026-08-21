<?php

namespace App\Enums;

/**
 * Operational status of a battery SKU. Distinct from `condition`:
 * a battery may be `New` in condition but `Quarantined` in status
 * pending QC.
 */
enum BatteryStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Quarantined = 'quarantined';
    case Reserved = 'reserved';
    case Discontinued = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Quarantined => 'Quarantined',
            self::Reserved => 'Reserved',
            self::Discontinued => 'Discontinued',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Quarantined => 'warning',
            self::Reserved => 'info',
            self::Discontinued => 'dark',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
