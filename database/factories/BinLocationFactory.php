<?php

namespace Database\Factories;

use App\Models\BinLocation;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BinLocation>
 */
class BinLocationFactory extends Factory
{
    public function definition(): array
    {
        $zone = Str::upper(fake()->randomLetter());
        $aisle = (string) fake()->numberBetween(1, 9);
        $shelf = (string) fake()->numberBetween(1, 20);

        return [
            'workshop_id' => Workshop::factory(),
            'code' => $zone.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999), 2, '0', STR_PAD_LEFT),
            'zone' => $zone,
            'aisle' => $aisle,
            'shelf' => $shelf,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
