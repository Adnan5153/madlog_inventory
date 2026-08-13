<?php

namespace Database\Factories;

use App\Models\JobCard;
use App\Models\JobCardPart;
use App\Models\Part;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobCardPart>
 */
class JobCardPartFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->randomFloat(2, 1, 6);

        return [
            'workshop_id' => Workshop::factory(),
            'job_card_id' => JobCard::factory(),
            'part_id' => Part::factory(),
            'inventory_item_id' => null,
            'issued_by' => User::factory(),
            'quantity' => $qty,
            'quantity_consumed' => fake()->randomFloat(2, 0, $qty),
            'quantity_returned' => fake()->randomFloat(2, 0, $qty),
            'unit_price' => fake()->randomFloat(2, 5, 80),
            'status' => fake()->randomElement(['reserved', 'consumed', 'returned', 'partial']),
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }
}
