<?php

namespace Database\Factories;

use App\Models\Part;
use App\Models\SerialNumber;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SerialNumber>
 */
class SerialNumberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'part_id' => Part::factory(),
            'serial' => strtoupper(fake()->bothify('SN-????????-####')),
            'status' => SerialNumber::STATUS_AVAILABLE,
            'purchased_at' => fake()->optional(0.5)->dateTimeBetween('-1 year', 'now'),
            'sold_at' => null,
        ];
    }
}