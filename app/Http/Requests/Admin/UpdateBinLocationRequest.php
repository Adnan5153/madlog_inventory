<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBinLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('bin_location')) ?? false;
    }

    public function rules(): array
    {
        $bin = $this->route('bin_location');
        $workshopId = $this->user()?->workshop_id ?? 0;

        return [
            'code'        => [
                'required', 'string', 'max:32',
                Rule::unique('bin_locations', 'code')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at')
                    ->ignore($bin->id),
            ],
            'zone'        => ['nullable', 'string', 'max:64'],
            'aisle'       => ['nullable', 'string', 'max:32'],
            'shelf'       => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active'   => ['required', 'boolean'],
        ];
    }
}