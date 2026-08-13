<?php

namespace Database\Factories;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workshop>
 */
class WorkshopFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'address' => fake()->streetAddress(),
            'phone' => fake()->e164PhoneNumber(),
            'email' => fake()->companyEmail(),
            'is_active' => true,
        ];
    }
}
