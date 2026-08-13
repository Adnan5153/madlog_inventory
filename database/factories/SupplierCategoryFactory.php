<?php

namespace Database\Factories;

use App\Models\SupplierCategory;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupplierCategory>
 */
class SupplierCategoryFactory extends Factory
{
    public function definition(): array
    {
        $pairs = [
            ['OEM', 'OEM'],
            ['Aftermarket', 'AMKT'],
            ['Electrical', 'ELEC'],
            ['Hydraulics', 'HYD'],
            ['Fluids & Lubricants', 'FLUID'],
            ['Tires & Wheels', 'TIRE'],
            ['Body & Paint', 'PAINT'],
        ];

        [$name, $code] = fake()->unique()->randomElement($pairs);

        return [
            'workshop_id' => Workshop::factory(),
            'name' => $name,
            'code' => $code.'-'.fake()->bothify('##'),
            'description' => fake()->optional(0.5)->sentence(),
            'is_active' => true,
        ];
    }
}