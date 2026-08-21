<?php

namespace App\Enums;

/**
 * Physical condition of the battery unit. Drives what can be done with
 * the stock — saleable, repairable, write-off.
 */
enum BatteryCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Used = 'used';
    case Refurbished = 'refurbished';
    case Damaged = 'damaged';
    case Defective = 'defective';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Good => 'Good',
            self::Used => 'Used',
            self::Refurbished => 'Refurbished',
            self::Damaged => 'Damaged',
            self::Defective => 'Defective',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'success',
            self::Good => 'primary',
            self::Refurbished => 'info',
            self::Used => 'secondary',
            self::Damaged, self::Defective, self::Expired => 'danger',
        };
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::New, self::Good, self::Refurbished], true);
    }

    public function isSellable(): bool
    {
        return in_array($this, [self::New, self::Good, self::Refurbished], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
