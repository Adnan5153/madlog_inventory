<?php

namespace Database\Factories;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'bin_location_id' => null,
            'received_by' => User::factory(),
            'grn_number' => 'GRN-'.date('Y').'-'.Str::upper(Str::random(6)),
            'supplier_invoice_number' => fake()->optional(0.6)->bothify('INV-####'),
            'status' => 'received',
            'received_at' => now(),
            'notes' => fake()->optional(0.4)->sentence(),
        ];
    }
}
