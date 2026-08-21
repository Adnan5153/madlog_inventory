<?php

namespace App\Http\Requests\Admin;

use App\Enums\ToolMaintenanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tool = $this->route('tool');

        return $tool !== null
            ? ($this->user()?->can('recordMaintenance', $tool) ?? false)
            : false;
    }

    public function rules(): array
    {
        $tool = $this->route('tool');
        $workshopId = $tool?->workshop_id ?? $this->user()?->workshop_id;

        return [
            'tool_id' => [
                'required', 'integer',
                Rule::exists('tools', 'id')->where('workshop_id', $workshopId),
            ],
            'type' => ['required', 'string', 'in:'.implode(',', ToolMaintenanceType::values())],
            'performed_by' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('workshop_id', $workshopId),
            ],
            'vendor' => ['nullable', 'string', 'max:160'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'performed_at' => ['required', 'date'],
            'next_due_at' => ['nullable', 'date', 'after:performed_at'],
            'description' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('tool') !== null && ! $this->filled('tool_id')) {
            $this->merge(['tool_id' => $this->route('tool')->id]);
        }
    }
}
