<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatteryStockAdjustmentRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'reference' => [
                'required', 'string', 'max:32',
                Rule::unique('battery_stock_adjustments', 'reference')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at'),
            ],
            'reason' => ['required', 'string', 'in:cycle_count,shrinkage,damage,found,manual'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.battery_id' => [
                'required', 'integer',
                Rule::exists('batteries', 'id')->where('workshop_id', $workshopId),
            ],
            'items.*.bin_id' => [
                'nullable', 'integer',
                Rule::exists('bin_locations', 'id')->where('workshop_id', $workshopId),
            ],
            'items.*.battery_inventory_item_id' => [
                'nullable', 'integer',
                Rule::exists('battery_inventory_items', 'id')->where('workshop_id', $workshopId),
            ],
            'items.*.counted_quantity' => ['required', 'numeric'],
            'items.*.quantity' => ['required', 'numeric'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForWorkshopScoping();
    }
}
