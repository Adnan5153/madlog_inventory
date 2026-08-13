<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) ?? false;
    }

    public function rules(): array
    {
        $part = $this->route('product');
        $workshopId = $this->user()?->workshop_id;

        return [
            'name'              => ['required', 'string', 'max:160'],
            'sku'               => [
                'nullable', 'string', 'max:64',
                Rule::unique('parts', 'sku')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($part->id),
            ],
            'oem_part_number'   => ['nullable', 'string', 'max:64'],
            'barcode'           => [
                'nullable', 'string', 'max:64',
                Rule::unique('parts', 'barcode')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($part->id),
            ],
            'description'       => ['nullable', 'string', 'max:5000'],
            'category_id'       => ['nullable', 'integer', 'exists:part_categories,id'],
            'brand_id'          => ['nullable', 'integer', 'exists:brands,id'],
            'unit_id'           => ['nullable', 'integer', 'exists:units,id'],
            'cost_price'        => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'sale_price'        => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'reorder_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_quantity'  => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active'         => ['required', 'boolean'],
        ];
    }
}