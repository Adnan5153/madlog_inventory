<?php

namespace Database\Factories;

use App\Models\Battery;
use App\Models\BatteryInventoryItem;
use App\Models\BatteryStockAdjustment;
use App\Models\BatteryStockAdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryStockAdjustmentItem>
 */
class BatteryStockAdjustmentItemFactory extends Factory
{
    public function definition(): array
    {
        $counted = fake()->randomFloat(2, 5, 100);
        $delta = fake()->randomFloat(2, -5, 5);

        return [
            'battery_stock_adjustment_id' => BatteryStockAdjustment::factory(),
            'battery_id' => Battery::factory(),
            'battery_inventory_item_id' => BatteryInventoryItem::factory(),
            'bin_id' => null,
            'quantity' => $delta,
            'counted_quantity' => $counted,
            'unit_cost' => fake()->randomFloat(2, 25, 350),
            'reason' => null,
        ];
    }
}
