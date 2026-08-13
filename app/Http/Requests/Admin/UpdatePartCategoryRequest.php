<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdatePartCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->user()->workshop_id ?? 0;
        $categoryId = $this->route('category')?->getKey();

        return [
            'name'        => ['required', 'string', 'max:120'],
            'slug'        => ['nullable', 'string', 'max:120', Rule::unique('part_categories', 'slug')->where('workshop_id', $workshopId)->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }
}