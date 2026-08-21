<?php

namespace App\Http\Requests\Admin;

use App\Models\Tool;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreToolCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tool = $this->route('tool');

        return $tool !== null
            ? ($this->user()?->can('checkout', $tool) ?? false)
            : false;
    }

    public function rules(): array
    {
        $tool = $this->route('tool');
        $workshopId = $tool?->workshop_id ?? $this->user()?->workshop_id;

        return [
            'tool_id' => [
                'required', 'integer',
                Rule::exists('tools', 'id')->where('workshop_id', $workshopId),
            ],
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('workshop_id', $workshopId),
            ],
            'expected_return_at' => ['nullable', 'date', 'after:now'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $toolId = $this->input('tool_id');
            if (! $toolId) {
                return;
            }

            $tool = Tool::find($toolId);
            if ($tool === null) {
                return;
            }

            if (! $tool->status->isCheckoutable()) {
                $v->errors()->add('tool_id', sprintf(
                    'Tool cannot be checked out from status "%s".',
                    $tool->status->label(),
                ));
            }

            if ($tool->checkouts()->whereNull('returned_at')->exists()) {
                $v->errors()->add('tool_id', 'Tool already has an open checkout.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('tool') !== null && ! $this->filled('tool_id')) {
            $this->merge(['tool_id' => $this->route('tool')->id]);
        }
    }
}
