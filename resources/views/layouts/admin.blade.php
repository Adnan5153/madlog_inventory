<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head-admin')
</head>
<body>
    <div class="admin-shell">
        <aside id="admin-sidebar" class="admin-sidebar" aria-label="Admin navigation">
            <a href="{{ route('admin.dashboard') }}" class="brand" wire:navigate>
                <i class="bi bi-tools" aria-hidden="true"></i>
                <span>{{ config('app.name', 'Madlog') }}</span>
            </a>

            @php
                /** @var \App\Models\User|null $adminUser */
                $adminUser = auth()->user();
                $adminWorkshopName = $adminUser?->workshop?->name;
            @endphp

            <x-admin.sidebar :active="request()->route()->getName()" />

            @if($adminWorkshopName)
                <div class="px-3 py-3 small text-secondary border-top border-secondary border-opacity-25 mt-auto">
                    <div class="text-uppercase fw-semibold" style="letter-spacing:.06em; color: rgba(255,255,255,.5);">
                        Workshop
                    </div>
                    <div class="text-white">{{ $adminWorkshopName }}</div>
                </div>
            @endif
        </aside>

        <header class="admin-topbar">
            <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            <h1 class="topbar-title">{{ $title ?? 'Admin' }}</h1>

            <div class="topbar-actions">
                @auth
                    <div class="user-chip">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span>{{ auth()->user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>
                            Log out
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        <div data-sidebar-backdrop class="d-lg-none" style="display:none; position:fixed; inset:0; background: rgba(0,0,0,.4); z-index: 1030;"></div>

        <main class="admin-main" id="admin-main" tabindex="-1">
            @isset($breadcrumb)
                {{ $breadcrumb }}
            @endisset

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>