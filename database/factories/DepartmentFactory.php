<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Maintenance', 'Engineering', 'Operations', 'Electrical',
            'Mechanical', 'Marine', 'Safety', 'Logistics',
        ]);

        return [
            'workshop_id' => Workshop::factory(),
            'name' => $name,
            'code' => Str::upper(Str::limit(Str::slug($name, ''), 6, '')).'-'.fake()->bothify('##'),
            'description' => fake()->optional(0.6)->sentence(),
            'manager_id' => User::factory(),
            'is_active' => true,
        ];
    }
}