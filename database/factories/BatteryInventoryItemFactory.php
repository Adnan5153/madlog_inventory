<?php

namespace Database\Factories;

use App\Models\Battery;
use App\Models\BatteryInventoryItem;
use App\Models\BinLocation;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryInventoryItem>
 */
class BatteryInventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'battery_id' => Battery::factory(),
            'bin_id' => BinLocation::factory(),
            'supplier_id' => null,
            'batch_number' => 'BATCH-'.fake()->numberBetween(10000, 99999),
            'quantity' => fake()->randomFloat(2, 0, 100),
            'reserved_quantity' => 0,
            'cost_price' => fake()->randomFloat(2, 25, 350),
            'expires_at' => fake()->optional(0.5)->dateTimeBetween('+1 year', '+5 years'),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'quantity' => fake()->randomFloat(2, 0, 2),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['quantity' => 0]);
    }
}
