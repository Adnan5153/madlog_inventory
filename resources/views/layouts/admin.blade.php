<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('partials.head-admin')

    {{-- Publish the admin route map so the global Command Bar can resolve
         hrefs from JS without an extra round-trip. Only the names that
         appear in `resources/js/app.js` COMMANDS array are needed, but
         publishing the full admin route map keeps it tidy. --}}
    @php
        $adminRouteMap = [
            'admin.dashboard'                  => route('admin.dashboard'),
            'admin.categories.index'          => route('admin.categories.index'),
            'admin.units.index'               => route('admin.units.index'),
            'admin.products.index'            => route('admin.products.index'),
            'admin.lubricants.index'          => route('admin.lubricants.index'),
            'admin.warehouses.index'          => route('admin.warehouses.index'),
            'admin.bin-locations.index'       => route('admin.bin-locations.index'),
            'admin.purchase-orders.index'     => route('admin.purchase-orders.index'),
            'admin.goods-receipts.index'      => route('admin.goods-receipts.index'),
            'admin.suppliers.index'           => route('admin.suppliers.index'),
            'admin.stock-adjustments.index'   => route('admin.stock-adjustments.index'),
            'admin.stock-transfers.index'     => route('admin.stock-transfers.index'),
            'admin.users.index'               => route('admin.users.index'),
            'admin.roles.index'               => route('admin.roles.index'),
            'admin.permissions.index'         => route('admin.permissions.index'),
            'admin.reports.valuation'         => route('admin.reports.valuation'),
            'admin.reports.low-stock'         => route('admin.reports.low-stock'),
            'admin.reports.movements'         => route('admin.reports.movements'),
            'admin.reports.top-consumed'      => route('admin.reports.top-consumed'),
            'admin.departments.index'         => route('admin.departments.index'),
            'admin.equipment.index'           => route('admin.equipment.index'),
            'admin.tools.dashboard'           => route('admin.tools.dashboard'),
            'admin.tools.index'               => route('admin.tools.index'),
            'admin.tool-categories.index'     => route('admin.tool-categories.index'),
            'admin.settings.edit'             => route('admin.settings.edit'),
            'admin.audit-logs.index'          => route('admin.audit-logs.index'),
        ];
    @endphp
    <script>
        window.madlogRoutes = @json($adminRouteMap);
    </script>
</head>
<body>
    <div class="admin-shell">
        <aside id="admin-sidebar" class="admin-sidebar" aria-label="Admin navigation">
            <a href="{{ route('admin.dashboard') }}" class="brand" wire:navigate
               title="{{ config('app.name', 'Madlog') }}">
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
                <div class="sidebar-footer px-3 py-3 small mt-auto"
                     title="{{ $adminWorkshopName }}">
                    <div class="sidebar-footer-label">Workshop</div>
                    <div class="sidebar-footer-name">{{ $adminWorkshopName }}</div>
                </div>
            @endif
        </aside>

        <header class="admin-topbar">
            <button type="button" class="sidebar-toggle" data-sidebar-toggle
                    aria-label="Toggle sidebar" aria-controls="admin-sidebar" aria-expanded="true">
                <i class="bi bi-list" aria-hidden="true" data-sidebar-toggle-icon></i>
            </button>

            <h1 class="topbar-title">{{ $title ?? 'Admin' }}</h1>

            <button type="button" class="cmd-bar-trigger" data-cmd-bar-open
                    aria-label="Open command bar (Ctrl+K)">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Search or jump to&hellip;</span>
                <span class="kbd-hint">
                    <kbd>Ctrl</kbd><kbd>K</kbd>
                </span>
            </button>

            <div class="topbar-actions">
                <div class="density-toggle" data-density-toggle role="group" aria-label="Table density">
                    <button type="button" data-density="comfortable" aria-pressed="true" title="Comfortable">
                        <i class="bi bi-list" aria-hidden="true"></i>
                    </button>
                    <button type="button" data-density="compact" aria-pressed="false" title="Compact">
                        <i class="bi bi-list-ol" aria-hidden="true"></i>
                    </button>
                </div>

                @auth
                    <div class="user-chip d-none d-md-flex">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span>{{ auth()->user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>
                            <span class="d-none d-md-inline">Log out</span>
                        </button>
                    </form>
                @endauth
            </div>
        </header>

        <div data-sidebar-backdrop class="d-lg-none" style="display:none; position:fixed; inset:0; background: rgba(15,23,42,.4); z-index: 1030;"></div>

        <main class="admin-main" id="admin-main" tabindex="-1">
            @isset($breadcrumb)
                {{ $breadcrumb }}
            @endisset

            @yield('content')
        </main>
    </div>

    {{-- Slide-over drawer host. The .madlog-drawer Offcanvas elements are
         appended into <body> on demand by app.js. --}}
    <div data-drawer-host></div>

    @stack('scripts')
</body>
</html>