<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use App\Models\Part;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Part::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'name' => ['required', 'string', 'max:160'],
            'sku' => [
                'nullable', 'string', 'max:64',
                Rule::unique('parts', 'sku')->where('workshop_id', $workshopId)->whereNull('deleted_at'),
            ],
            'oem_part_number' => ['nullable', 'string', 'max:64'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('parts', 'barcode')->where('workshop_id', $workshopId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'equipment_compatibility' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:part_categories,id'],
            'brand' => ['nullable', 'string', 'max:120'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'bin_location_id' => [
                'nullable', 'integer',
                Rule::exists('bin_locations', 'id')->where('workshop_id', $workshopId),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'supplier_id' => [
                'nullable', 'integer',
                Rule::exists('suppliers', 'id')->where('workshop_id', $workshopId),
            ],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'reorder_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForWorkshopScoping();
    }
}
