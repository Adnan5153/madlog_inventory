<?php

namespace App\Enums;

/**
 * Operational status of a lubricant SKU. Distinct from `is_active`:
 * `status` reflects business lifecycle, `is_active` is the on/off
 * switch for dropdown visibility.
 */
enum LubricantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Quarantined = 'quarantined';
    case Discontinued = 'discontinued';
    case Recalled = 'recalled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Quarantined => 'Quarantined',
            self::Discontinued => 'Discontinued',
            self::Recalled => 'Recalled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Quarantined => 'warning',
            self::Discontinued => 'dark',
            self::Recalled => 'danger',
        };
    }

    public function isOperational(): bool
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
