<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use App\Models\EquipmentConsumable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEquipmentConsumableRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('equipment_consumable')) ?? false;
    }

    public function rules(): array
    {
        $model = $this->route('equipment_consumable');
        $workshopId = $model instanceof EquipmentConsumable
            ? (int) $model->workshop_id
            : $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'expected_replacement_at' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lockWorkshopFromRouteModel('equipment_consumable');
    }
}
