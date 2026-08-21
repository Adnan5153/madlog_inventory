<?php

namespace App\Http\Requests\Admin;

use App\Models\EquipmentConsumable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared request for the three action endpoints (consume / replace / remove).
 * The action type is part of the route, so the validator only whitelists
 * the values that make sense for that endpoint via type-specific rules
 * inside the controller.
 */
class RecordConsumableEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $consumable = $this->route('equipment_consumable');
        if (! $consumable instanceof EquipmentConsumable) {
            return false;
        }

        $ability = match ($this->route()->getName()) {
            'admin.equipment-consumables.consume' => 'consume',
            'admin.equipment-consumables.replace' => 'replace',
            'admin.equipment-consumables.remove' => 'remove',
            default => 'update',
        };

        return $this->user()?->can($ability, $consumable) ?? false;
    }

    public function rules(): array
    {
        $workshopId = (int) ($this->route('equipment_consumable')?->workshop_id ?? 0);

        $base = [
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999.9999'],
            'bin_id' => [
                'nullable', 'integer',
                Rule::exists('bin_locations', 'id')->where('workshop_id', $workshopId),
            ],
            'performed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        // Replace-only: a new resource must be selected.
        if ($this->route()->getName() === 'admin.equipment-consumables.replace') {
            $base['new_resource_type'] = [
                'required', 'string',
                Rule::in(EquipmentConsumable::allowedResourceTypes()),
            ];
            $base['new_resource_id'] = ['required', 'integer', 'min:1'];
        }

        // Remove-only: optional return-to-stock flag.
        if ($this->route()->getName() === 'admin.equipment-consumables.remove') {
            $base['return_to_stock'] = ['nullable', 'boolean'];
            $base['return_quantity'] = ['nullable', 'numeric', 'gte:0', 'max:999999.999'];
        }

        return $base;
    }
}
