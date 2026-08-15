<?php

namespace App\Http\Requests\Admin;

use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Supplier::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->user()?->workshop_id;

        return [
            'name' => ['required', 'string', 'max:160', Rule::unique('suppliers', 'name')->where('workshop_id', $workshopId)->whereNull('deleted_at')],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:64'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'supplier_category_id' => ['nullable', 'integer', 'exists:supplier_categories,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
