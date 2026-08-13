<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('unit')) ?? false;
    }

    public function rules(): array
    {
        $unitId = $this->route('unit')?->getKey();

        return [
            'name'              => ['required', 'string', 'max:120'],
            'short_code'        => ['required', 'string', 'max:8', Rule::unique('units', 'short_code')->ignore($unitId)],
            'description'       => ['nullable', 'string', 'max:500'],
            'decimal_precision' => ['required', 'integer', 'between:0,6'],
            'is_active'         => ['required', 'boolean'],
        ];
    }
}