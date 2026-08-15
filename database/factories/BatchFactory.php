<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Part;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'part_id' => Part::factory(),
            'batch_number' => 'BATCH-'.strtoupper(fake()->bothify('????####')),
            'manufactured_at' => fake()->optional(0.7)->dateTimeBetween('-2 years', 'now'),
            'expires_at' => fake()->optional(0.7)->dateTimeBetween('now', '+3 years'),
            'initial_quantity' => 100,
            'current_quantity' => 100,
            'status' => Batch::STATUS_ACTIVE,
        ];
    }
}
