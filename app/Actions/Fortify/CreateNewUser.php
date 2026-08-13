<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * In local environments, the registration form also accepts role and
     * workshop_id so test data can be seeded without going through the admin
     * invite flow. In production (or any non-local env) those extra inputs
     * are silently dropped and the new user always lands as a public visitor
     * (role=null, workshop_id=null).
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $rules = [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ];

        $devExtras = [];
        if (app()->isLocal()) {
            $devExtras = [
                'role' => ['nullable', 'string', Rule::in(User::roles())],
                'workshop_id' => ['nullable', 'integer', Rule::exists(Workshop::class, 'id')],

                // Conditional requirement: if role is admin/staff, a workshop
                // is required; global admins explicitly use workshop_id = null.
                'workshop_id.required_if' => 'nullable',
            ];
        }

        Validator::make($input, [...$rules, ...$devExtras])->validate();

        $attributes = [
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ];

        if (app()->isLocal()) {
            $requestedRole = $input['role'] ?? null;
            $requestedWorkshop = $input['workshop_id'] ?? null;

            // Validate the conditional business rule ourselves so the message
            // is informative (the framework's `required_if` would otherwise
            // complain about the missing field on every request, including
            // public signups).
            if (in_array($requestedRole, User::roles(), true) && $requestedRole !== null) {
                $attributes['role'] = $requestedRole;

                // Admin without a workshop = global admin.
                $attributes['workshop_id'] = $requestedRole === User::ROLE_ADMIN && ! $requestedWorkshop
                    ? null
                    : ($requestedWorkshop ?: null);
            }

            // Auto-verify emails in local so test users can hit role-gated
            // routes immediately (skips the "verify your email" detour).
            $attributes['email_verified_at'] = now();
        }

        return User::create($attributes);
    }
}
