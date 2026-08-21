<?php

namespace Database\Factories;

use App\Enums\BatteryApplication;
use App\Enums\BatteryChemistry;
use App\Enums\BatteryCondition;
use App\Enums\BatteryStatus;
use App\Models\Battery;
use App\Models\BinLocation;
use App\Models\Supplier;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Battery>
 */
class BatteryFactory extends Factory
{
    public function definition(): array
    {
        $chemistry = fake()->randomElement(BatteryChemistry::cases());

        $name = match ($chemistry) {
            BatteryChemistry::LithiumIronPhosphate, BatteryChemistry::LithiumIon => fake()->randomElement([
                'Lithium 12V 100Ah', 'Lithium 12V 200Ah', 'LiFePO4 12V 50Ah',
                'Lithium 24V 100Ah', 'LiFePO4 12V 150Ah',
            ]),
            BatteryChemistry::Agm, BatteryChemistry::Efb => fake()->randomElement([
                'AGM Start-Stop 12V 70Ah', 'AGM 12V 80Ah', 'EFB 12V 60Ah',
                'AGM Deep-Cycle 12V 100Ah',
            ]),
            default => fake()->randomElement([
                'Battery 12V 60Ah', 'Battery 12V 75Ah', 'Battery 12V 100Ah',
                'Battery 12V 45Ah', 'Marine 12V 110Ah', 'Truck 24V 150Ah',
                'Industrial 12V 200Ah', 'Forklift 24V 250Ah',
            ]),
        };

        return [
            'workshop_id' => Workshop::factory(),
            'battery_code' => 'BTY-'.strtoupper(Str::random(6)),
            'sku' => Str::upper(Str::random(3)).'-'.fake()->numberBetween(1000, 9999),
            'name' => $name,
            'barcode' => (string) fake()->numberBetween(1000000000000, 9999999999999),
            'brand' => fake()->randomElement(['Bosch', 'Yuasa', 'Varta', 'Exide', 'Optima', 'ACDelco', 'Denso']),
            'manufacturer_part_number' => 'MPN-'.strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'battery_type' => $chemistry->value,
            'application_type' => fake()->randomElement(BatteryApplication::cases())->value,
            'condition' => BatteryCondition::New->value,
            'status' => BatteryStatus::Active->value,
            'voltage' => fake()->randomElement([6.00, 12.00, 24.00, 48.00]),
            'capacity_ah' => fake()->randomFloat(2, 30, 250),
            'cold_cranking_amps' => fake()->numberBetween(300, 1100),
            'reserve_capacity' => fake()->numberBetween(60, 300),
            'terminal_type' => fake()->randomElement(['top', 'side', 'stud', 'flag']),
            'length_mm' => fake()->randomFloat(2, 150, 500),
            'width_mm' => fake()->randomFloat(2, 120, 240),
            'height_mm' => fake()->randomFloat(2, 150, 240),
            'weight_kg' => fake()->randomFloat(3, 4, 60),
            'polarity' => fake()->randomElement(['positive', 'negative']),
            'cost_price' => fake()->randomFloat(2, 25, 350),
            'supplier_id' => null,
            'bin_location_id' => null,
            'reorder_threshold' => fake()->numberBetween(2, 8),
            'reorder_quantity' => fake()->numberBetween(10, 40),
            'warranty_period_months' => fake()->numberBetween(6, 36),
            'warranty_expiry' => fake()->optional(0.7)->dateTimeBetween('+3 months', '+3 years'),
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
        return $this->state(fn () => ['status' => BatteryStatus::Quarantined->value]);
    }

    public function chemistry(BatteryChemistry $c): static
    {
        return $this->state(fn () => ['battery_type' => $c->value]);
    }

    public function application(BatteryApplication $a): static
    {
        return $this->state(fn () => ['application_type' => $a->value]);
    }
}
