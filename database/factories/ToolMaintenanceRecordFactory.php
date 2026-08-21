<?php

namespace Database\Factories;

use App\Enums\ToolMaintenanceType;
use App\Models\Tool;
use App\Models\ToolMaintenanceRecord;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolMaintenanceRecord>
 */
class ToolMaintenanceRecordFactory extends Factory
{
    public function definition(): array
    {
        $performedAt = fake()->dateTimeBetween('-1 year', '-1 day');

        return [
            'workshop_id' => Workshop::factory(),
            'tool_id' => Tool::factory(),
            'type' => fake()->randomElement(ToolMaintenanceType::cases())->value,
            'performed_by' => User::factory(),
            'vendor' => fake()->optional(0.4)->company(),
            'cost' => fake()->optional(0.6)->randomFloat(2, 10, 800),
            'performed_at' => $performedAt,
            'next_due_at' => fake()->optional(0.5)->dateTimeBetween('+1 month', '+1 year'),
            'description' => fake()->sentence(),
        ];
    }

    public function preventive(): static
    {
        return $this->state(fn () => ['type' => ToolMaintenanceType::Preventive->value]);
    }

    public function repair(): static
    {
        return $this->state(fn () => ['type' => ToolMaintenanceType::Repair->value]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'next_due_at' => now()->subDays(fake()->numberBetween(2, 60)),
        ]);
    }
}
