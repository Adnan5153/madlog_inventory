<?php

namespace Database\Factories;

use App\Models\GoodsReceiptItem;
use App\Models\Part;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptItem>
 */
class GoodsReceiptItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 50);

        return [
            'goods_receipt_id' => null,        // must be set explicitly when creating
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'part_id' => Part::factory(),
            'bin_location_id' => null,
            'quantity_ordered' => $qty,
            'quantity_received' => $qty,
            'damaged_quantity' => 0,
            'batch_number' => fake()->optional(0.3)->bothify('BATCH-####'),
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('now', '+2 years'),
            'unit_cost' => fake()->randomFloat(2, 1, 200),
            'notes' => null,
        ];
    }
}
