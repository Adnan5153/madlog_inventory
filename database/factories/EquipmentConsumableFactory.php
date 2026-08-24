<?php

namespace Database\Factories;

use App\Models\Battery;
use App\Models\Equipment;
use App\Models\EquipmentConsumable;
use App\Models\Lubricant;
use App\Models\Part;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentConsumable>
 */
class EquipmentConsumableFactory extends Factory
{
    public function definition(): array
    {
        // Polymorphic resource_type / resource_id: pick a Part by default.
        // Tests can override via ->forPart(), ->forBattery(), ->forLubricant().
        $resourceType = Part::class;
        $part = Part::factory()->create();

        return [
            'workshop_id' => Workshop::factory(),
            'equipment_id' => Equipment::factory(),
            'resource_type' => $resourceType,
            'resource_id' => $part->id,
            'assigned_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'expected_replacement_at' => fake()->optional(0.6)->dateTimeBetween('+1 month', '+3 years'),
            'notes' => fake()->optional(0.3)->sentence(),
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    public function forPart(Part $part): static
    {
        return $this->state(fn () => [
            'workshop_id' => $part->workshop_id,
            'resource_type' => Part::class,
            'resource_id' => $part->id,
        ]);
    }

    public function forBattery(Battery $battery): static
    {
        return $this->state(fn () => [
            'workshop_id' => $battery->workshop_id,
            'resource_type' => Battery::class,
            'resource_id' => $battery->id,
        ]);
    }

    public function forLubricant(Lubricant $lubricant): static
    {
        return $this->state(fn () => [
            'workshop_id' => $lubricant->workshop_id,
            'resource_type' => Lubricant::class,
            'resource_id' => $lubricant->id,
        ]);
    }

    public function dueForReplacement(): static
    {
        return $this->state(fn () => [
            'expected_replacement_at' => fake()->dateTimeBetween('-7 days', '+14 days'),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'expected_replacement_at' => fake()->dateTimeBetween('-90 days', '-1 day'),
        ]);
    }
}
