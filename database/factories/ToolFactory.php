<?php

namespace Database\Factories;

use App\Enums\ToolCondition;
use App\Enums\ToolStatus;
use App\Models\BinLocation;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Torque Wrench 1/2"', 'Torque Wrench 3/8"', 'Digital Torque Wrench',
            'Impact Driver 18V', 'Impact Wrench 1/2"', 'Pneumatic Impact Gun',
            'Cordless Power Drill', 'Angle Grinder 4.5"', 'Heat Gun',
            'OBD-II Diagnostic Scanner', 'Multimeter', 'Compression Tester',
            'Floor Jack 3T', 'Hydraulic Jack 2T', 'Engine Hoist 2T',
            'Bearing Puller Set', 'Pneumatic Compression Tester',
            'Diagnostic Tablet', 'Socket Set 1/2"', 'Socket Set 3/8"',
        ]);

        return [
            'workshop_id' => Workshop::factory(),
            'tool_code' => 'TL-'.strtoupper(Str::random(6)),
            'name' => $name,
            'category_id' => null,
            'brand' => fake()->randomElement(['Snap-on', 'Matco', 'Mac Tools', 'Bosch', 'Makita', 'DeWalt', 'Milwaukee', 'Stanley', 'Hilti', 'Fluke', 'Launch', 'Autel']),
            'model' => strtoupper(Str::random(3)).'-'.fake()->numberBetween(100, 9999),
            'serial_number' => 'SN'.fake()->unique()->bothify('??######'),
            'barcode' => (string) fake()->unique()->numberBetween(1000000000000, 9999999999999),
            'qr_code' => 'QR-'.strtoupper(Str::random(10)),
            'description' => fake()->sentence(),
            'condition' => ToolCondition::Good->value,
            'status' => ToolStatus::Available->value,
            'current_holder_user_id' => null,
            'is_active' => true,
            'bin_id' => null,
            'supplier_id' => null,
            'purchase_date' => fake()->optional(0.8)->dateTimeBetween('-5 years', '-1 month'),
            'purchase_price' => fake()->randomFloat(2, 50, 2000),
            'warranty_expiry' => fake()->optional(0.5)->dateTimeBetween('-6 months', '+3 years'),
            'notes' => null,
        ];
    }

    public function forCategory(ToolCategory $category): static
    {
        return $this->state(fn () => [
            'workshop_id' => $category->workshop_id,
            'category_id' => $category->id,
        ]);
    }

    public function forSupplier(Supplier $supplier): static
    {
        return $this->state(fn () => [
            'workshop_id' => $supplier->workshop_id,
            'supplier_id' => $supplier->id,
        ]);
    }

    public function withBin(BinLocation $bin): static
    {
        return $this->state(fn () => [
            'workshop_id' => $bin->workshop_id,
            'bin_id' => $bin->id,
        ]);
    }

    public function available(): static
    {
        return $this->state(fn () => [
            'status' => ToolStatus::Available->value,
            'current_holder_user_id' => null,
        ]);
    }

    public function checkedOut(User $user): static
    {
        return $this->state(fn () => [
            'status' => ToolStatus::CheckedOut->value,
            'current_holder_user_id' => $user->id,
        ]);
    }

    public function underMaintenance(): static
    {
        return $this->state(fn () => ['status' => ToolStatus::UnderMaintenance->value]);
    }

    public function damaged(): static
    {
        return $this->state(fn () => [
            'status' => ToolStatus::Damaged->value,
            'condition' => ToolCondition::Damaged->value,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => ['status' => ToolStatus::Lost->value]);
    }

    public function retired(): static
    {
        return $this->state(fn () => ['status' => ToolStatus::Retired->value]);
    }

    public function outOfService(): static
    {
        return $this->state(fn () => ['status' => ToolStatus::OutOfService->value]);
    }

    public function inCondition(ToolCondition $c): static
    {
        return $this->state(fn () => ['condition' => $c->value]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
