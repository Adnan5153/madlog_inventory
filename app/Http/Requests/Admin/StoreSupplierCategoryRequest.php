<?php

namespace App\Http\Requests\Admin;

use App\Models\SupplierCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSupplierCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SupplierCategory::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->user()?->workshop_id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:32', Rule::unique('supplier_categories', 'code')->where('workshop_id', $workshopId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('code') && $this->filled('name')) {
            $this->merge(['code' => Str::slug($this->input('name'))]);
        }
    }
}
