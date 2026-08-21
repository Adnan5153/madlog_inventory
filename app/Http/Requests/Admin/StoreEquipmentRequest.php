<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Equipment::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'name' => ['required', 'string', 'max:160'],
            'asset_number' => [
                'nullable', 'string', 'max:64',
                Rule::unique('equipment', 'asset_number')->where('workshop_id', $workshopId),
            ],
            'equipment_type' => ['nullable', 'string', 'max:64'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'purchase_date' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'retired', 'disposed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'bin_location_id' => ['nullable', 'integer', 'exists:bin_locations,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForWorkshopScoping();
    }
}
