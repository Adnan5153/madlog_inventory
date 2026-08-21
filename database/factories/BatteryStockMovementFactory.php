<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Battery;
use App\Models\BatteryStockMovement;
use App\Models\BinLocation;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryStockMovement>
 */
class BatteryStockMovementFactory extends Factory
{
    public function definition(): array
    {
        /** @var StockMovementType $type */
        $type = fake()->randomElement(StockMovementType::cases());
        $sign = $type->isInbound() ? 1 : -1;
        $magnitude = fake()->randomFloat(2, 1, 25);

        return [
            'workshop_id' => Workshop::factory(),
            'battery_id' => Battery::factory(),
            'bin_id' => BinLocation::factory(),
            'user_id' => User::factory(),
            'battery_inventory_item_id' => null,
            'type' => $type,
            'quantity' => $sign * $magnitude,
            'unit_cost' => fake()->randomFloat(2, 25, 350),
            'reference_type' => null,
            'reference_id' => null,
            'reason' => $type->requiresReason() ? fake()->sentence() : null,
            'occurred_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
