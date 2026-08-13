<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreWorkshopRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only global admins (no workshop binding) can create workshops.
        $user = $this->user();
        return $user instanceof User && $user->isAdmin() && $user->workshop_id === null;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:160'],
            'slug'    => ['nullable', 'string', 'max:160', Rule::unique('workshops', 'slug')->whereNull('deleted_at')],
            'address' => ['nullable', 'string', 'max:500'],
            'phone'   => ['nullable', 'string', 'max:64'],
            'email'   => ['nullable', 'email', 'max:160'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }
}