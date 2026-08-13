<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'transfer_number' => 'TRF-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'status' => StockTransfer::STATUS_DRAFT,
            'source_bin_id' => null,
            'destination_bin_id' => null,
            'transferred_by' => User::factory(),
            'received_by' => null,
            'dispatched_at' => null,
            'received_at' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}