<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateToolCategoryRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tool_category')) ?? false;
    }

    public function rules(): array
    {
        $category = $this->route('tool_category');
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('tool_categories', 'name')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($category->id),
            ],
            'slug' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lockWorkshopFromRouteModel('tool_category');
    }
}
