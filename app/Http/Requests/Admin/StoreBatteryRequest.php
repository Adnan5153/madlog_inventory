<?php

namespace App\Http\Requests\Admin;

use App\Enums\BatteryApplication;
use App\Enums\BatteryChemistry;
use App\Enums\BatteryCondition;
use App\Enums\BatteryStatus;
use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use App\Models\Battery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatteryRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Battery::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'battery_code' => [
                'required', 'string', 'max:64',
                Rule::unique('batteries', 'battery_code')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at'),
            ],
            'sku' => [
                'nullable', 'string', 'max:64',
                Rule::unique('batteries', 'sku')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:160'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('batteries', 'barcode')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at'),
            ],
            'brand' => ['nullable', 'string', 'max:120'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'battery_type' => ['required', 'string', 'in:'.implode(',', BatteryChemistry::values())],
            'application_type' => ['nullable', 'string', 'in:'.implode(',', BatteryApplication::values())],
            'condition' => ['required', 'string', 'in:'.implode(',', BatteryCondition::values())],
            'status' => ['required', 'string', 'in:'.implode(',', BatteryStatus::values())],
            'voltage' => ['required', 'numeric', 'min:0', 'max:1000'],
            'capacity_ah' => ['required', 'numeric', 'min:0', 'max:100000'],
            'cold_cranking_amps' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reserve_capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'terminal_type' => ['nullable', 'string', 'max:32'],
            'length_mm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width_mm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'height_mm' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'polarity' => ['nullable', 'string', 'in:positive,negative'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'supplier_id' => [
                'nullable', 'integer',
                Rule::exists('suppliers', 'id')->where('workshop_id', $workshopId),
            ],
            'bin_location_id' => [
                'nullable', 'integer',
                Rule::exists('bin_locations', 'id')->where('workshop_id', $workshopId),
            ],
            'reorder_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'warranty_period_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'warranty_expiry' => ['nullable', 'date', 'after_or_equal:today'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForWorkshopScoping();
    }
}
