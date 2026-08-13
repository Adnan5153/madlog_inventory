<?php

namespace Database\Factories;

use App\Models\PartCategory;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PartCategory>
 */
class PartCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Brakes', 'Engine', 'Filters', 'Fluids', 'Electrical',
            'Suspension', 'Transmission', 'Belts & Hoses', 'Lighting',
            'Body', 'Cooling', 'Exhaust',
            'Steering', 'Tires', 'Exhaust', 'Lubricants',
        ]);

        return [
            'workshop_id' => Workshop::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
        ];
    }
}
