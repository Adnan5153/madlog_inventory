<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\HandlesWorkshopScoping;
use App\Models\BinLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBinLocationRequest extends FormRequest
{
    use HandlesWorkshopScoping;

    public function authorize(): bool
    {
        return $this->user()?->can('create', BinLocation::class) ?? false;
    }

    public function rules(): array
    {
        $workshopId = $this->effectiveWorkshopId();

        return [
            'workshop_id' => $this->workshopRule(),
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('bin_locations', 'code')
                    ->where('workshop_id', $workshopId)
                    ->whereNull('deleted_at'),
            ],
            'zone' => ['nullable', 'string', 'max:64'],
            'aisle' => ['nullable', 'string', 'max:32'],
            'shelf' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForWorkshopScoping();
    }
}
