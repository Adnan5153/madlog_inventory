<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        {{-- @chisel-passkeys --}}
        <x-passkey-verify />
        {{-- @end-chisel-passkeys --}}

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            {{--
                Dev-only demo-account picker. A real <select> so the user picks
                one option and both the email + password auto-fill. Renders only
                when APP_ENV=local so production builds never expose it.
            --}}
            @php
                $demoAccounts = app()->isLocal()
                    ? \App\Models\User::query()
                        ->whereIn('role', [\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_STAFF])
                        ->whereNull('deleted_at')
                        ->with('workshop:id,name')
                        ->orderByRaw("CASE role WHEN 'admin' THEN 0 ELSE 1 END")
                        ->orderBy('workshop_id')
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($u) => [
                            'email' => $u->email,
                            'password' => 'password',
                            'role' => $u->role,
                            'workshop' => $u->workshop?->name,
                            'is_global' => $u->isGlobalAdmin(),
                        ])
                        ->values()
                        ->all()
                    : [];
            @endphp

            @if(app()->isLocal() && count($demoAccounts) > 0)
                <!-- Demo account dropdown (local env only) -->
                <div class="flex flex-col gap-2">
                    <label for="demo-account" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Quick login (dev)') }}
                    </label>
                    <select
                        id="demo-account"
                        class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                        <option value="">— Select a demo account —</option>
                        @foreach($demoAccounts as $account)
                            <option
                                value="{{ $account['email'] }}"
                                data-password="{{ $account['password'] }}"
                                data-role="{{ $account['role'] }}"
                                data-workshop="{{ $account['workshop'] ?? '' }}"
                                data-global="{{ $account['is_global'] ? '1' : '0' }}"
                            >
                                {{ $account['email'] }}
                                — {{ ucfirst($account['role']) }}{{ $account['is_global'] ? ' (global)' : ($account['workshop'] ? ' — '.$account['workshop'] : '') }}
                            </option>
                        @endforeach
                    </select>
                    <p id="demo-hint" class="text-xs text-zinc-500 dark:text-zinc-400 hidden">
                        <span id="demo-hint-role" class="font-medium"></span>
                        <span id="demo-hint-workshop"></span>
                        — password auto-filled.
                    </p>
                </div>
            @endif

            <!-- Email Address -->
            <flux:input
                id="email"
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    id="password"
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        {{-- @chisel-registration --}}
        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
        {{-- @end-chisel-registration --}}
    </div>

    @if(app()->isLocal() && count($demoAccounts) > 0)
        @push('scripts')
            <script>
                (function () {
                    const picker = document.getElementById('demo-account');
                    const emailInput = document.getElementById('email');
                    const passwordInput = document.getElementById('password');
                    const hint = document.getElementById('demo-hint');
                    const hintRole = document.getElementById('demo-hint-role');
                    const hintWorkshop = document.getElementById('demo-hint-workshop');
                    if (!picker || !emailInput || !passwordInput) return;

                    function applySelection() {
                        const option = picker.options[picker.selectedIndex];
                        if (!option || !option.value) {
                            if (hint) hint.classList.add('hidden');
                            return;
                        }

                        emailInput.value = option.value;
                        emailInput.dispatchEvent(new Event('input', { bubbles: true }));

                        passwordInput.value = option.dataset.password || '';
                        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));

                        if (hint && hintRole) {
                            const role = option.dataset.role || '';
                            const isGlobal = option.dataset.global === '1';
                            const workshop = option.dataset.workshop || '';
                            hintRole.textContent = role === 'admin'
                                ? (isGlobal ? 'Global admin' : 'Workshop admin')
                                : 'Staff';
                            hintWorkshop.textContent = workshop ? ' — ' + workshop : '';
                            hint.classList.remove('hidden');
                        }
                    }

                    picker.addEventListener('change', applySelection);
                })();
            </script>
        @endpush
    @endif
</x-layouts::auth>