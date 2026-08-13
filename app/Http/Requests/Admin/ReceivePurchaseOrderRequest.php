<?php

namespace App\Http\Requests\Admin;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $po = $this->route('purchaseOrder');
        return ($po instanceof PurchaseOrder) && ($this->user()?->can('receive', $po) ?? false);
    }

    public function rules(): array
    {
        return [
            'bin_location_id' => ['nullable', 'integer', 'exists:bin_locations,id'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'items.*.damaged_quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'items.*.bin_location_id' => ['nullable', 'integer', 'exists:bin_locations,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:64'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }
}