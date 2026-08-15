<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentItem>
 */
class StockAdjustmentItemFactory extends Factory
{
    public function definition(): array
    {
        $before = fake()->randomFloat(2, 5, 100);
        $delta = fake()->randomFloat(2, -5, 5);

        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'before_quantity' => $before,
            'adjustment_quantity' => $delta,
            'after_quantity' => $before + $delta,
            'unit_cost' => fake()->randomFloat(2, 1, 100),
        ];
    }
}
