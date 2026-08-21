<?php

namespace Database\Factories;

use App\Enums\LubricantApplication;
use App\Enums\LubricantPackageType;
use App\Enums\LubricantStatus;
use App\Enums\LubricantType;
use App\Enums\LubricantViscosity;
use App\Models\BinLocation;
use App\Models\Lubricant;
use App\Models\Supplier;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lubricant>
 */
class LubricantFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(LubricantType::cases());
        $application = fake()->randomElement(LubricantApplication::cases());

        $name = match ($application) {
            LubricantApplication::EngineOil => fake()->randomElement([
                'Engine Oil 5W-30 5L', 'Engine Oil 10W-40 1L', 'Engine Oil 5W-40 4L',
                'Engine Oil 0W-20 5L', 'Engine Oil 15W-40 20L',
            ]),
            LubricantApplication::GearOil => fake()->randomElement([
                'Gear Oil 75W-90 1L', 'Gear Oil 80W-90 5L', 'Differential Oil 85W-140 1L',
                'Transmission Gear Oil 1L',
            ]),
            LubricantApplication::TransmissionFluid => fake()->randomElement([
                'ATF Dexron III 1L', 'ATF Mercon V 5L', 'Manual Transmission Fluid 1L',
                'CVT Fluid 4L',
            ]),
            LubricantApplication::HydraulicOil => fake()->randomElement([
                'Hydraulic Oil ISO VG 46 20L', 'Hydraulic Oil ISO VG 32 5L',
                'Hydraulic Oil ISO VG 68 20L', 'Hydraulic Oil ISO VG 100 200L',
            ]),
            LubricantApplication::Grease => fake()->randomElement([
                'Lithium Grease NLGI 2 400g', 'Multi-Purpose Grease 1kg',
                'High-Temp Grease 500g', 'Bearing Grease 5kg',
            ]),
            LubricantApplication::Coolant => fake()->randomElement([
                'Coolant Concentrate 5L', 'Long-Life Coolant 20L', 'Antifreeze 1L',
                'Organic Coolant 5L',
            ]),
            LubricantApplication::BrakeFluid => fake()->randomElement([
                'DOT 4 Brake Fluid 1L', 'DOT 5.1 Brake Fluid 500ml',
                'DOT 3 Brake Fluid 5L',
            ]),
            LubricantApplication::CompressorOil => fake()->randomElement([
                'Compressor Oil ISO VG 100 5L', 'Compressor Oil ISO VG 46 20L',
                'Synthetic Compressor Oil 5L',
            ]),
            default => fake()->randomElement([
                'Industrial Lubricant 5L', 'Cutting Fluid 20L', 'Way Oil ISO VG 68 5L',
                'Spindle Oil ISO VG 32 5L',
            ]),
        };

        return [
            'workshop_id' => Workshop::factory(),
            'lubricant_code' => 'LUB-'.strtoupper(Str::random(6)),
            'sku' => Str::upper(Str::random(3)).'-'.fake()->numberBetween(1000, 9999),
            'name' => $name,
            'barcode' => (string) fake()->numberBetween(1000000000000, 9999999999999),
            'brand' => fake()->randomElement(['Castrol', 'Shell', 'Mobil', 'Total', 'Valvoline', 'Motul', 'Liqui Moly', 'BP', 'Fuchs']),
            'manufacturer' => fake()->randomElement(['BP', 'Shell', 'ExxonMobil', 'Chevron', 'Fuchs Petrolub', 'Petronas']),
            'manufacturer_part_number' => 'MPN-'.strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'lubricant_type' => $type->value,
            'viscosity_grade' => fake()->randomElement(LubricantViscosity::cases())->value,
            'application_type' => $application->value,
            'status' => LubricantStatus::Active->value,
            'oem_specification' => fake()->optional(0.6)->randomElement([
                'MB-Approval 229.51', 'VW 504 00 / 507 00', 'BMW LL-04',
                'Porsche C30', 'Ford WSS-M2C913-C', 'GM dexos2',
            ]),
            'acea_specification' => fake()->optional(0.6)->randomElement([
                'A3/B4', 'A5/B5', 'C2', 'C3', 'E4', 'E7', 'E9',
            ]),
            'api_specification' => fake()->optional(0.6)->randomElement([
                'SN', 'SN PLUS', 'SP', 'CK-4', 'CJ-4', 'GL-4', 'GL-5',
            ]),
            'iso_grade' => fake()->optional(0.4)->randomElement(['VG 32', 'VG 46', 'VG 68', 'VG 100']),
            'nlgi_grade' => $application === LubricantApplication::Grease
                ? fake()->randomElement(['NLGI 0', 'NLGI 1', 'NLGI 2', 'NLGI 3'])
                : null,
            'package_type' => fake()->randomElement(LubricantPackageType::cases())->value,
            'package_size' => fake()->randomElement([0.5, 1.0, 4.0, 5.0, 20.0, 60.0, 200.0]),
            'package_unit' => fake()->randomElement(['L', 'kg', 'gal']),
            'cost_price' => fake()->randomFloat(2, 5, 250),
            'supplier_id' => null,
            'bin_location_id' => null,
            'reorder_threshold' => fake()->numberBetween(2, 8),
            'reorder_quantity' => fake()->numberBetween(10, 40),
            'is_active' => true,
            'notes' => null,
        ];
    }

    public function forSupplier(Supplier $supplier): static
    {
        return $this->state(fn () => [
            'workshop_id' => $supplier->workshop_id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function withBin(BinLocation $bin): static
    {
        return $this->state(fn () => [
            'workshop_id' => $bin->workshop_id,
            'bin_location_id' => $bin->id,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'reorder_threshold' => 10,
            'reorder_quantity' => 20,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'reorder_threshold' => 0,
            'reorder_quantity' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function quarantined(): static
    {
        return $this->state(fn () => ['status' => LubricantStatus::Quarantined->value]);
    }

    public function base(LubricantType $t): static
    {
        return $this->state(fn () => ['lubricant_type' => $t->value]);
    }

    public function viscosity(LubricantViscosity $v): static
    {
        return $this->state(fn () => ['viscosity_grade' => $v->value]);
    }

    public function application(LubricantApplication $a): static
    {
        return $this->state(fn () => ['application_type' => $a->value]);
    }
}
