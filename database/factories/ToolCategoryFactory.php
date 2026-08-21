<?php

namespace Database\Factories;

use App\Models\ToolCategory;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ToolCategory>
 */
class ToolCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Hand Tools',
            'Power Tools',
            'Diagnostic Tools',
            'Special Service Tools',
            'Lifting Equipment',
            'Measuring Equipment',
            'Electrical Tools',
            'Pneumatic Tools',
            'Workshop Equipment',
            'Safety Equipment',
        ]);

        return [
            'workshop_id' => Workshop::factory(),
            'name' => $name.' '.fake()->unique()->numberBetween(1, 9999),
            'slug' => Str::slug($name),
            'description' => fake()->optional(0.6)->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
