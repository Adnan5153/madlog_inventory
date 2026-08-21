<?php

namespace Database\Factories;

use App\Enums\ToolCheckoutStatus;
use App\Enums\ToolCondition;
use App\Models\Tool;
use App\Models\ToolCheckout;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<ToolCheckout>
 */
class ToolCheckoutFactory extends Factory
{
    public function definition(): array
    {
        $checkedOutAt = fake()->dateTimeBetween('-30 days', '-1 day');

        return [
            'workshop_id' => Workshop::factory(),
            'tool_id' => Tool::factory(),
            'user_id' => User::factory(),
            'issued_by' => null,
            'checked_out_at' => $checkedOutAt,
            'expected_return_at' => Carbon::parse($checkedOutAt)->addDays(7),
            'returned_at' => null,
            'received_by' => null,
            'purpose' => fake()->optional(0.7)->randomElement([
                'Job #1042', 'Brake service', 'Engine swap', 'Wheel alignment',
                'Diagnostic check', 'Battery replacement',
            ]),
            'notes' => fake()->optional(0.4)->sentence(),
            'condition_at_return' => null,
            'status' => ToolCheckoutStatus::Open->value,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'returned_at' => null,
            'received_by' => null,
            'condition_at_return' => null,
            'status' => ToolCheckoutStatus::Open->value,
        ]);
    }

    public function closed(ToolCondition $condition = ToolCondition::Good): static
    {
        $checkedOutAt = fake()->dateTimeBetween('-30 days', '-2 days');

        return $this->state(fn () => [
            'checked_out_at' => $checkedOutAt,
            'returned_at' => Carbon::parse($checkedOutAt)->addDays(fake()->numberBetween(1, 5)),
            'condition_at_return' => $condition->value,
            'status' => ToolCheckoutStatus::Closed->value,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'returned_at' => null,
            'received_by' => null,
            'condition_at_return' => null,
            'expected_return_at' => now()->subDays(fake()->numberBetween(2, 14)),
            'status' => ToolCheckoutStatus::Overdue->value,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => [
            'workshop_id' => $user->workshop_id,
            'user_id' => $user->id,
        ]);
    }

    public function issuedBy(User $user): static
    {
        return $this->state(fn () => [
            'workshop_id' => $user->workshop_id,
            'issued_by' => $user->id,
        ]);
    }
}
