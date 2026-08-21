<?php

namespace App\Enums;

/**
 * Intended application / use case for a lubricant. Used for filtering
 * and for routing the product into the right workshop context.
 */
enum LubricantApplication: string
{
    case EngineOil = 'engine_oil';
    case GearOil = 'gear_oil';
    case TransmissionFluid = 'transmission_fluid';
    case HydraulicOil = 'hydraulic_oil';
    case Grease = 'grease';
    case Coolant = 'coolant';
    case BrakeFluid = 'brake_fluid';
    case CompressorOil = 'compressor_oil';
    case Industrial = 'industrial';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EngineOil => 'Engine Oil',
            self::GearOil => 'Gear Oil',
            self::TransmissionFluid => 'Transmission Fluid',
            self::HydraulicOil => 'Hydraulic Oil',
            self::Grease => 'Grease',
            self::Coolant => 'Coolant',
            self::BrakeFluid => 'Brake Fluid',
            self::CompressorOil => 'Compressor Oil',
            self::Industrial => 'Industrial',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EngineOil => 'primary',
            self::GearOil => 'info',
            self::TransmissionFluid => 'primary',
            self::HydraulicOil => 'success',
            self::Grease => 'warning',
            self::Coolant => 'info',
            self::BrakeFluid => 'danger',
            self::CompressorOil => 'secondary',
            self::Industrial => 'dark',
            self::Other => 'secondary',
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
