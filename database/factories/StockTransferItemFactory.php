<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransferItem>
 */
class StockTransferItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'stock_transfer_id' => StockTransfer::factory(),
            'part_id' => Part::factory(),
            'batch_number' => fake()->optional(0.3)->bothify('BATCH-####'),
            'quantity' => fake()->randomFloat(2, 1, 50),
        ];
    }
}