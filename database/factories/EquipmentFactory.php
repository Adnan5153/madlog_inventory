<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'name' => fake()->randomElement([
                'Hydraulic Press', 'Engine Hoist', 'Diagnostic Scanner',
                'Wheel Balancer', 'Brake Lathe', 'Tire Changer',
                'Welding Station', 'Air Compressor', 'Pneumatic Impact Wrench',
            ]),
            'asset_number' => 'EQ-'.fake()->unique()->bothify('####-???'),
            'equipment_type' => fake()->randomElement(['Diagnostic', 'Lift', 'Tool', 'Welder', 'Compressor']),
            'manufacturer' => fake()->company(),
            'model' => fake()->bothify('Model-###'),
            'serial_number' => fake()->unique()->bothify('SN########'),
            'purchase_date' => fake()->dateTimeBetween('-5 years', '-30 days'),
            'warranty_expires_at' => fake()->optional(0.7)->dateTimeBetween('now', '+2 years'),
            'status' => Equipment::STATUS_ACTIVE,
            'notes' => fake()->optional(0.3)->sentence(),
            'is_active' => true,
        ];
    }

    public function maintenance(): static
    {
        return $this->state(['status' => Equipment::STATUS_MAINTENANCE]);
    }
}