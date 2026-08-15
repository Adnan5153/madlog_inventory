<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('department')) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->user()->workshop_id ?? 0;
        $departmentId = $this->route('department')?->getKey();

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'string', 'max:32', Rule::unique('departments', 'code')->where('workshop_id', $workshopId)->ignore($departmentId)],
            'description' => ['nullable', 'string', 'max:500'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
