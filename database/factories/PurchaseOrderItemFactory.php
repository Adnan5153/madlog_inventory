<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        $ordered = fake()->randomFloat(2, 5, 50);
        $cost = fake()->randomFloat(2, 5, 80);
        $received = fake()->randomFloat(2, 0, $ordered);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'part_id' => Part::factory(),
            'quantity_ordered' => $ordered,
            'quantity_received' => $received,
            'unit_cost' => $cost,
            'line_total' => round($ordered * $cost, 2),
        ];
    }
}
