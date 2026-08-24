<?php

namespace Database\Factories;

use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\Supplier;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'part_id' => Part::factory(),
            'bin_id' => BinLocation::factory(),
            'supplier_id' => Supplier::factory(),
            'batch_number' => 'BATCH-'.fake()->numberBetween(10000, 99999),
            'quantity' => fake()->randomFloat(2, 0, 100),
            'reserved_quantity' => 0,
            'cost_price' => fake()->randomFloat(2, 1, 50),
            'expires_at' => fake()->optional(0.7)->dateTimeBetween('+6 months', '+3 years'),
        ];
    }

    public function lowStock(): static
    {
        return $this->state(fn () => [
            'quantity' => fake()->randomFloat(2, 0, 3),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => ['quantity' => 0]);
    }

    public function forBin(BinLocation $bin): static
    {
        return $this->state(fn () => [
            'workshop_id' => $bin->workshop_id,
            'bin_id' => $bin->id,
        ]);
    }

    public function forPart(Part $part): static
    {
        return $this->state(fn () => [
            'workshop_id' => $part->workshop_id,
            'part_id' => $part->id,
        ]);
    }
}
