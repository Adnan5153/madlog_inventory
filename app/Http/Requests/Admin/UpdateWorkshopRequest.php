<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkshopRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->isAdmin() && $user->workshop_id === null;
    }

    public function rules(): array
    {
        $workshop = $this->route('warehouse');

        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => [
                'nullable', 'string', 'max:160',
                Rule::unique('workshops', 'slug')->whereNull('deleted_at')->ignore($workshop->id),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:160'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
