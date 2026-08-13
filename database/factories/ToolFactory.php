<?php

namespace Database\Factories;

use App\Models\BinLocation;
use App\Models\Tool;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'name' => fake()->randomElement([
                'Torque Wrench 1/2"', 'Impact Driver', 'Floor Jack 3T',
                'OBD-II Scanner', 'Pneumatic Gun', 'Compression Tester',
                'Hydraulic Press', 'Diagnostic Tablet', 'Bearing Puller',
            ]),
            'asset_tag' => 'AST-'.fake()->unique()->numberBetween(1000, 9999),
            'serial_number' => 'SN'.fake()->unique()->bothify('??######'),
            'bin_id' => BinLocation::factory(),
            'value' => fake()->randomFloat(2, 50, 2000),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
