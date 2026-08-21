<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @php
            // Dev-only: expose role + workshop selectors so testers can spin up
            // admin / staff accounts without going through the invite flow.
            $showRoleWorkshop = app()->isLocal();
            $workshops = $showRoleWorkshop
                ? \App\Models\Workshop::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : collect();
        @endphp

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            @if($showRoleWorkshop)
                {{-- Dev-only: role + workshop pickers to match the users table. --}}
                <div class="flex flex-col gap-4 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">
                            {{ __('Local development only') }}
                        </p>
                        <p class="mt-1 text-xs text-amber-800 dark:text-amber-200">
                            {{ __('Pick a role and workshop so the new account matches a row in the users table. In production this block is hidden and every sign-up is a public visitor.') }}
                        </p>
                    </div>

                    <!-- Role -->
                    <div class="flex flex-col gap-2">
                        <label for="role" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Role') }}
                        </label>
                        <select
                            id="role"
                            name="role"
                            class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                            <option value="" {{ old('role') === null ? 'selected' : '' }}>
                                — {{ __('Public visitor (no workshop)') }} —
                            </option>
                            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>
                                {{ __('Admin') }}
                            </option>
                            <option value="staff" {{ old('role') === 'staff' ? 'selected' : '' }}>
                                {{ __('Staff') }}
                            </option>
                        </select>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Staff work in a single workshop. Admins can be workshop-scoped or global (workshop = none).') }}
                        </p>
                    </div>

                    <!-- Workshop -->
                    <div class="flex flex-col gap-2">
                        <label for="workshop_id" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Workshop') }}
                            <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">
                                ({{ __('Required for staff; optional for admin (omit for global admin)') }})
                            </span>
                        </label>
                        <select
                            id="workshop_id"
                            name="workshop_id"
                            class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-zinc-700 dark:bg-zinc-100"
                        >
                            <option value="">
                                — {{ __('None (global admin)') }} —
                            </option>
                            @foreach($workshops as $w)
                                <option value="{{ $w->id }}" {{ (string) old('workshop_id') === (string) $w->id ? 'selected' : '' }}>
                                    {{ $w->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>

    {{-- Role/workshop conditional requirement is handled in resources/js/app.js --}}
</x-layouts::auth>