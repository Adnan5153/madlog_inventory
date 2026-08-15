<?php

namespace App\Http\Requests\Admin;

use App\Models\BinLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBinLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BinLocation::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->user()?->workshop_id ?? 0;

        return [
            'code' => ['required', 'string', 'max:32', Rule::unique('bin_locations', 'code')->where('workshop_id', $workshopId)->whereNull('deleted_at')],
            'zone' => ['nullable', 'string', 'max:64'],
            'aisle' => ['nullable', 'string', 'max:32'],
            'shelf' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
