<?php

namespace Database\Factories;

use App\Models\Tool;
use App\Models\ToolCheckout;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolCheckout>
 */
class ToolCheckoutFactory extends Factory
{
    public function definition(): array
    {
        $checkedOutAt = fake()->dateTimeBetween('-14 days', '-1 day');
        $returned = fake()->boolean(70);

        return [
            'workshop_id' => Workshop::factory(),
            'tool_id' => Tool::factory(),
            'user_id' => User::factory(),
            'issued_by' => User::factory(),
            'checked_out_at' => $checkedOutAt,
            'returned_at' => $returned ? fake()->dateTimeBetween($checkedOutAt, 'now') : null,
            'expected_return_at' => fake()->dateTimeBetween($checkedOutAt, '+7 days'),
            'notes' => fake()->optional(0.4)->sentence(),
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'returned_at' => null,
        ]);
    }
}
