<?php

namespace Database\Factories;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'adjustment_number' => 'ADJ-'.date('Y').'-'.strtoupper(Str::random(6)),
            'status' => StockAdjustment::STATUS_DRAFT,
            'reason' => fake()->randomElement(['cycle_count', 'shrinkage', 'damage', 'found']),
            'notes' => fake()->optional(0.5)->sentence(),
            'requested_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'applied_at' => null,
        ];
    }
}
