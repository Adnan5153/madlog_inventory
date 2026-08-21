<?php

namespace Database\Factories;

use App\Models\Lubricant;
use App\Models\LubricantInventoryItem;
use App\Models\LubricantStockAdjustment;
use App\Models\LubricantStockAdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LubricantStockAdjustmentItem>
 */
class LubricantStockAdjustmentItemFactory extends Factory
{
    public function definition(): array
    {
        $counted = fake()->randomFloat(2, 5, 100);
        $delta = fake()->randomFloat(2, -5, 5);

        return [
            'lubricant_stock_adjustment_id' => LubricantStockAdjustment::factory(),
            'lubricant_id' => Lubricant::factory(),
            'lubricant_inventory_item_id' => LubricantInventoryItem::factory(),
            'bin_id' => null,
            'quantity' => $delta,
            'counted_quantity' => $counted,
            'unit_cost' => fake()->randomFloat(2, 5, 250),
            'reason' => null,
        ];
    }
}
