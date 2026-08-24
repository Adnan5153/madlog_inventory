<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Brake Pad — Front', 'Brake Pad — Rear', 'Engine Oil 5W-30',
            'Engine Oil 10W-40', 'Air Filter', 'Cabin Filter',
            'Spark Plug NGK', 'Coolant 1L', 'Coolant 5L', 'Timing Belt',
            'Serpentine Belt', 'Brake Fluid DOT-4', 'Brake Fluid DOT-5.1',
            'Wiper Blade 22"', 'Wiper Blade 18"', 'Fuel Filter',
            'Oil Filter', 'Battery 12V 60Ah', 'Headlight Bulb H4',
            'Cabin Air Filter', 'Drive Belt', 'Alternator Belt',
            'Thermostat', 'Radiator Hose', 'Ball Joint', 'Tie Rod End',
        ]);

        return [
            'workshop_id' => Workshop::factory(),
            'category_id' => PartCategory::factory(),
            'sku' => Str::upper(Str::random(3)).'-'.fake()->unique()->numberBetween(1000, 9999),
            'oem_part_number' => 'OEM-'.Str::upper(Str::random(8)),
            'brand' => fake()->randomElement(['Bosch', 'NGK', 'Denso', 'ACDelco', 'Mahle', 'Continental', 'Valeo', 'Brembo']),
            'barcode' => (string) fake()->unique()->numberBetween(1000000000000, 9999999999999),
            'name' => $name,
            'description' => fake()->sentence(),
            'reorder_threshold' => fake()->numberBetween(2, 10),
            'reorder_quantity' => fake()->numberBetween(10, 50),
            'cost_price' => fake()->randomFloat(2, 1, 80),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function forCategory(PartCategory $category): static
    {
        return $this->state(fn () => [
            'workshop_id' => $category->workshop_id,
            'category_id' => $category->id,
        ]);
    }
}
