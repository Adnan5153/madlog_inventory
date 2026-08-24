<?php

namespace Database\Factories;

use App\Enums\EquipmentConsumableStatus;
use App\Enums\EquipmentConsumableType;
use App\Models\BinLocation;
use App\Models\EquipmentConsumable;
use App\Models\EquipmentConsumableAssignment;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentConsumableAssignment>
 *
 * Models a single lifecycle event for one equipment_consumable.
 * Use the `assigned()`, `installed()`, `consumed()`, `replaced()`,
 * `removed()` states to seed the event you want — the default
 * produces a generic Assigned row.
 */
class EquipmentConsumableAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $performedAt = fake()->dateTimeBetween('-30 days', '-1 hour');

        return [
            'workshop_id' => Workshop::factory(),
            'equipment_consumable_id' => EquipmentConsumable::factory(),
            'type' => EquipmentConsumableType::Assigned->value,
            'status' => EquipmentConsumableStatus::Assigned->value,
            'quantity' => fake()->randomFloat(3, 0.5, 50),
            'unit_id' => Unit::factory(),
            'unit_cost' => fake()->randomFloat(4, 1, 200),
            'total_cost' => null,
            'performed_by' => User::factory(),
            'performed_at' => $performedAt,
            'previous_assignment_id' => null,
            'bin_id' => null,
            'stock_movement_type' => null,
            'stock_movement_id' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn () => [
            'type' => EquipmentConsumableType::Assigned->value,
            'status' => EquipmentConsumableStatus::Assigned->value,
        ]);
    }

    public function installed(): static
    {
        return $this->state(fn () => [
            'type' => EquipmentConsumableType::Installed->value,
            'status' => EquipmentConsumableStatus::Installed->value,
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn () => [
            'type' => EquipmentConsumableType::Consumed->value,
            'status' => EquipmentConsumableStatus::Consumed->value,
            'stock_movement_type' => 'part',
            'stock_movement_id' => StockMovement::factory(),
        ]);
    }

    public function replaced(): static
    {
        return $this->state(fn () => [
            'type' => EquipmentConsumableType::Replaced->value,
            'status' => EquipmentConsumableStatus::Removed->value,
        ]);
    }

    public function removed(): static
    {
        return $this->state(fn () => [
            'type' => EquipmentConsumableType::Removed->value,
            'status' => EquipmentConsumableStatus::Removed->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => EquipmentConsumableStatus::Cancelled->value,
        ]);
    }

    public function withBin(BinLocation $bin): static
    {
        return $this->state(fn () => [
            'workshop_id' => $bin->workshop_id,
            'bin_id' => $bin->id,
        ]);
    }

    public function by(User $user): static
    {
        return $this->state(fn () => [
            'workshop_id' => $user->workshop_id,
            'performed_by' => $user->id,
        ]);
    }
}
