<?php

namespace App\Enums;

/**
 * Battery chemistry / technology. Stored as a string on the batteries
 * table; the values are stable and used in form validation, reports and
 * CSV exports.
 */
enum BatteryChemistry: string
{
    case LeadAcid = 'lead_acid';
    case Agm = 'agm';
    case Efb = 'efb';
    case Gel = 'gel';
    case LithiumIon = 'lithium_ion';
    case LithiumIronPhosphate = 'lithium_iron_phosphate';
    case NiCd = 'ni_cd';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::LeadAcid => 'Lead Acid',
            self::Agm => 'AGM',
            self::Efb => 'EFB',
            self::Gel => 'Gel',
            self::LithiumIon => 'Lithium-ion',
            self::LithiumIronPhosphate => 'LiFePO4',
            self::NiCd => 'Ni-Cd',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LithiumIon, self::LithiumIronPhosphate => 'success',
            self::Agm, self::Efb => 'info',
            self::Gel => 'primary',
            self::NiCd => 'warning',
            self::LeadAcid => 'secondary',
            self::Other => 'dark',
        };
    }

    public function isLithium(): bool
    {
        return $this === self::LithiumIon || $this === self::LithiumIronPhosphate;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
