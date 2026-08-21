<?php

namespace Database\Factories;

use App\Enums\LubricantStockAdjustmentStatus;
use App\Models\LubricantStockAdjustment;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LubricantStockAdjustment>
 */
class LubricantStockAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'reference' => 'LSA-'.date('Y').'-'.strtoupper(Str::random(6)),
            'status' => LubricantStockAdjustmentStatus::Pending,
            'reason' => fake()->randomElement(['cycle_count', 'shrinkage', 'damage', 'found', 'manual', 'spillage']),
            'notes' => fake()->optional(0.5)->sentence(),
            'requested_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LubricantStockAdjustmentStatus::Approved,
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => LubricantStockAdjustmentStatus::Rejected,
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }
}
