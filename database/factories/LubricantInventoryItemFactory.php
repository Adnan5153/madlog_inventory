<?php

namespace Database\Factories;

use App\Models\BinLocation;
use App\Models\Lubricant;
use App\Models\LubricantInventoryItem;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LubricantInventoryItem>
 */
class LubricantInventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'lubricant_id' => Lubricant::factory(),
            'bin_id' => BinLocation::factory(),
            'supplier_id' => null,
            'batch_number' => 'BATCH-'.fake()->numberBetween(10000, 99999),
            'quantity' => fake()->randomFloat(2, 0, 100),
            'reserved_quantity' => 0,
            'cost_price' => fake()->randomFloat(2, 5, 250),
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
