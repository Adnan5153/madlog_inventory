<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('department')) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->effectiveWorkshopId();
        $departmentId = $this->route('department')?->getKey();

        return [
            'workshop_id' => $this->workshopRule(),
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('departments', 'code')->where('workshop_id', $workshopId)->ignore($departmentId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lockWorkshopFromRouteModel('department');
    }
}
