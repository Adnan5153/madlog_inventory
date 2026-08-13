<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'po_number' => 'PO-'.date('Y').'-'.Str::upper(Str::random(6)),
            'workshop_id' => Workshop::factory(),
            'supplier_id' => Supplier::factory(),
            'created_by' => User::factory(),
            'approved_by' => null,
            'status' => fake()->randomElement([
                'draft', 'submitted', 'approved',
                'partially_received', 'received', 'cancelled',
            ]),
            'order_date' => fake()->dateTimeBetween('-60 days', 'now'),
            'expected_date' => fake()->dateTimeBetween('now', '+30 days'),
            'received_date' => null,
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => 'received',
            'received_date' => now(),
        ]);
    }
}
