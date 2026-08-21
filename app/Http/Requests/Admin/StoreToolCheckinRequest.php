<?php

namespace App\Http\Requests\Admin;

use App\Enums\ToolCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tool = $this->route('tool');

        return $tool !== null
            ? ($this->user()?->can('checkin', $tool) ?? false)
            : false;
    }

    public function rules(): array
    {
        $tool = $this->route('tool');
        $workshopId = $tool?->workshop_id ?? $this->user()?->workshop_id;

        return [
            'received_by' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('workshop_id', $workshopId),
            ],
            'condition_at_return' => [
                'required', 'string',
                'in:'.implode(',', ToolCondition::values()),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
