<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use App\Models\EquipmentConsumable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentConsumableRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('create', EquipmentConsumable::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'equipment_id' => [
                'required', 'integer',
                Rule::exists('equipment', 'id')->where('workshop_id', $workshopId),
            ],
            'resource_type' => [
                'required', 'string',
                Rule::in(EquipmentConsumable::allowedResourceTypes()),
            ],
            'resource_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999.9999'],
            'bin_id' => [
                'nullable', 'integer',
                Rule::exists('bin_locations', 'id')->where('workshop_id', $workshopId),
            ],
            'assigned_at' => ['required', 'date'],
            'expected_replacement_at' => ['nullable', 'date', 'after_or_equal:assigned_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Cross-field check: the resource_id must belong to the same workshop
     * and be of the right concrete type. Performed after the row-level
     * checks above have run.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $type = $this->input('resource_type');
            $id = $this->input('resource_id');
            $workshopId = $this->effectiveWorkshopId();

            if (! $type || ! $id) {
                return;
            }

            $allowed = EquipmentConsumable::allowedResourceTypes();
            if (! in_array($type, $allowed, true)) {
                $v->errors()->add('resource_type', 'Invalid resource type.');
                return;
            }

            $table = match ($type) {
                \App\Models\Part::class => 'parts',
                \App\Models\Battery::class => 'batteries',
                \App\Models\Lubricant::class => 'lubricants',
            };

            $exists = \DB::table($table)
                ->where('id', $id)
                ->where('workshop_id', $workshopId)
                ->exists();

            if (! $exists) {
                $v->errors()->add('resource_id', 'Selected resource does not belong to this workshop.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForWorkshopScoping();
    }
}
