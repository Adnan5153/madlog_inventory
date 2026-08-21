<?php

namespace App\Enums;

/**
 * Outer container / packaging format a lubricant is sold in. Combined
 * with `package_size` + `package_unit` to describe the SKU.
 */
enum LubricantPackageType: string
{
    case Bottle = 'bottle';
    case Drum = 'drum';
    case Pail = 'pail';
    case Can = 'can';
    case Bag = 'bag';
    case Carton = 'carton';
    case Ibc = 'ibc';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bottle => 'Bottle',
            self::Drum => 'Drum',
            self::Pail => 'Pail',
            self::Can => 'Can',
            self::Bag => 'Bag',
            self::Carton => 'Carton',
            self::Ibc => 'IBC Tote',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Bottle, self::Can => 'primary',
            self::Drum, self::Pail, self::Ibc => 'info',
            self::Bag, self::Carton => 'secondary',
            self::Other => 'dark',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
