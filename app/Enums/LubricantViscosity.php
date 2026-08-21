<?php

namespace App\Enums;

/**
 * Viscosity grade of a lubricant. Covers both SAE engine-oil grades and
 * ISO industrial-oil VG grades, as well as "None" for greases or
 * ungraded products.
 */
enum LubricantViscosity: string
{
    case Sae5w30 = 'sae_5w_30';
    case Sae5w40 = 'sae_5w_40';
    case Sae10w30 = 'sae_10w_30';
    case Sae10w40 = 'sae_10w_40';
    case Sae15w40 = 'sae_15w_40';
    case Sae20w50 = 'sae_20w_50';
    case IsoVg32 = 'iso_vg_32';
    case IsoVg46 = 'iso_vg_46';
    case IsoVg68 = 'iso_vg_68';
    case IsoVg100 = 'iso_vg_100';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Sae5w30 => 'SAE 5W-30',
            self::Sae5w40 => 'SAE 5W-40',
            self::Sae10w30 => 'SAE 10W-30',
            self::Sae10w40 => 'SAE 10W-40',
            self::Sae15w40 => 'SAE 15W-40',
            self::Sae20w50 => 'SAE 20W-50',
            self::IsoVg32 => 'ISO VG 32',
            self::IsoVg46 => 'ISO VG 46',
            self::IsoVg68 => 'ISO VG 68',
            self::IsoVg100 => 'ISO VG 100',
            self::None => '— None —',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'secondary',
            default => 'primary',
        };
    }

    public function isSae(): bool
    {
        return in_array($this, [
            self::Sae5w30, self::Sae5w40,
            self::Sae10w30, self::Sae10w40,
            self::Sae15w40, self::Sae20w50,
        ], true);
    }

    public function isIso(): bool
    {
        return in_array($this, [
            self::IsoVg32, self::IsoVg46, self::IsoVg68, self::IsoVg100,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
