<?php

namespace App\Http\Requests\Admin;

use App\Enums\ToolMaintenanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToolMaintenanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('maintenance_record')) ?? false;
    }

    public function rules(): array
    {
        $record = $this->route('maintenance_record');
        $tool = $this->route('tool') ?? $record?->tool;
        $workshopId = $tool?->workshop_id ?? $this->user()?->workshop_id;

        return [
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
}
