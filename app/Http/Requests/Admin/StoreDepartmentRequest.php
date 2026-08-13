<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Department::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->user()->workshop_id ?? 0;

        return [
            'name'        => ['required', 'string', 'max:120'],
            'code'        => ['required', 'string', 'max:32', Rule::unique('departments', 'code')->where('workshop_id', $workshopId)],
            'description' => ['nullable', 'string', 'max:500'],
            'manager_id'  => ['nullable', 'integer', 'exists:users,id'],
            'is_active'   => ['required', 'boolean'],
        ];
    }
}