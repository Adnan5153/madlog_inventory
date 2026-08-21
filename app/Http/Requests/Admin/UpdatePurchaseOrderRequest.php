<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        $po = $this->route('purchaseOrder');

        return ($po instanceof PurchaseOrder) && ($this->user()?->can('update', $po) ?? false);
    }

    public function rules(): array
    {
        return [
            'workshop_id' => $this->workshopRule(),
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'integer', 'exists:parts,id'],
            'items.*.quantity_ordered' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lockWorkshopFromRouteModel('purchaseOrder');
    }
}
