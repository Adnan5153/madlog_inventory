<?php

namespace App\Enums;

/**
 * Intended application / market for a battery. Helps users find the
 * right unit for their vehicle or equipment.
 */
enum BatteryApplication: string
{
    case Automotive = 'automotive';
    case Truck = 'truck';
    case Marine = 'marine';
    case Industrial = 'industrial';
    case HeavyEquipment = 'heavy_equipment';
    case Forklift = 'forklift';
    case Generator = 'generator';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Automotive => 'Automotive',
            self::Truck => 'Truck',
            self::Marine => 'Marine',
            self::Industrial => 'Industrial',
            self::HeavyEquipment => 'Heavy equipment',
            self::Forklift => 'Forklift',
            self::Generator => 'Generator',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Automotive, self::Truck => 'primary',
            self::Marine => 'info',
            self::Forklift, self::HeavyEquipment => 'warning',
            self::Industrial, self::Generator => 'secondary',
            self::Other => 'dark',
        };
    }

    public function isVehicle(): bool
    {
        return $this === self::Automotive
            || $this === self::Truck
            || $this === self::Marine
            || $this === self::HeavyEquipment;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $a) => $a->value, self::cases());
    }
}
