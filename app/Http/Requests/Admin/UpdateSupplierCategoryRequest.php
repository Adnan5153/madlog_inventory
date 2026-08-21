<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierCategoryRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('supplier_category')) ?? false;
    }

    public function rules(): array
    {
        $cat = $this->route('supplier_category');
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'name' => ['required', 'string', 'max:120'],
            'code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('supplier_categories', 'code')
                    ->where('workshop_id', $workshopId)
                    ->ignore($cat->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->lockWorkshopFromRouteModel('supplier_category');
    }
}
