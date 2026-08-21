<?php

namespace App\Http\Requests\Admin;

use App\Enums\LubricantApplication;
use App\Enums\LubricantPackageType;
use App\Enums\LubricantStatus;
use App\Enums\LubricantType;
use App\Enums\LubricantViscosity;
use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLubricantRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lubricant')) ?? false;
    }

    public function rules(): array
    {
        $lubricant = $this->route('lubricant');
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'lubricant_code' => [
                'required', 'string', 'max:64',
                Rule::unique('lubricants', 'lubricant_code')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($lubricant->id),
            ],
            'sku' => [
                'nullable', 'string', 'max:64',
                Rule::unique('lubricants', 'sku')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($lubricant->id),
            ],
            'name' => ['required', 'string', 'max:160'],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('lubricants', 'barcode')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($lubricant->id),
            ],
            'brand' => ['nullable', 'string', 'max:120'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'manufacturer_part_number' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'lubricant_type' => ['required', 'string', 'in:'.implode(',', LubricantType::values())],
            'viscosity_grade' => ['nullable', 'string', 'in:'.implode(',', LubricantViscosity::values())],
            'application_type' => ['nullable', 'string', 'in:'.implode(',', LubricantApplication::values())],
            'status' => ['required', 'string', 'in:'.implode(',', LubricantStatus::values())],
            'oem_specification' => ['nullable', 'string', 'max:128'],
            'acea_specification' => ['nullable', 'string', 'max:64'],
            'api_specification' => ['nullable', 'string', 'max:64'],
            'iso_grade' => ['nullable', 'string', 'max:32'],
            'nlgi_grade' => ['nullable', 'string', 'max:32'],
            'package_type' => ['required', 'string', 'in:'.implode(',', LubricantPackageType::values())],
            'package_size' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'package_unit' => ['required', 'string', 'max:16'],
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
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lockWorkshopFromRouteModel('lubricant');
    }
}
