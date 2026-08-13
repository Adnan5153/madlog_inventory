<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\BinLocation;
use App\Models\Part;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        /** @var StockMovementType $type */
        $type = fake()->randomElement(StockMovementType::cases());
        $sign = $type->isInbound() ? 1 : -1;
        $magnitude = fake()->randomFloat(2, 1, 25);

        return [
            'workshop_id' => Workshop::factory(),
            'part_id' => Part::factory(),
            'bin_id' => BinLocation::factory(),
            'user_id' => User::factory(),
            'inventory_item_id' => null,
            'type' => $type,
            'quantity' => $sign * $magnitude,
            'unit_cost' => fake()->randomFloat(2, 1, 50),
            'reference_type' => null,
            'reference_id' => null,
            'reason' => $type->requiresReason() ? fake()->sentence() : null,
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
