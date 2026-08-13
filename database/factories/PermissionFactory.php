<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $group = fake()->randomElement(['products', 'warehouses', 'inventory']);
        $verb = fake()->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'name' => "{$group}.{$verb}.test." . Str::random(4),
            'group' => $group,
            'description' => fake()->sentence(),
        ];
    }
}