<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        // Pair of (name, short_code) so the unique short_code never
        // collides on its own. Names are short enough that they're
        // almost always unique.
        $pairs = [
            ['Piece', 'pc'],
            ['Box', 'box'],
            ['Set', 'set'],
            ['Liter', 'L'],
            ['Milliliter', 'ml'],
            ['Kilogram', 'kg'],
            ['Gram', 'g'],
            ['Meter', 'm'],
            ['Roll', 'roll'],
            ['Pack', 'pack'],
        ];

        $index = fake()->unique()->numberBetween(0, count($pairs) - 1);
        [$name, $code] = $pairs[$index];

        return [
            'name' => $name,
            'short_code' => $code,
            'description' => fake()->optional(0.6)->sentence(),
            'decimal_precision' => in_array($name, ['Piece', 'Box', 'Set'], true) ? 0 : 2,
            'is_active' => true,
        ];
    }
}
