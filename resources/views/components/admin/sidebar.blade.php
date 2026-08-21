@php
    /**
     * Admin sidebar. Renders the section/nav list and marks the active
     * item based on the current route name.
     *
     * Filtered by the current user's `role` enum for now; when the
     * granular permission system lands in P6, this switches to
     * checking user->hasPermission() per group.
     *
     * @var string $active  The currently active route name.
     */

    $groups = [
        [
            'title' => 'Overview',
            'items' => [
                ['name' => 'admin.dashboard',     'icon' => 'bi-speedometer2',   'label' => 'Dashboard'],
            ],
        ],
        [
            'title' => 'Catalog',
            'items' => [
                ['name' => 'admin.categories.index', 'icon' => 'bi-tags',         'label' => 'Categories'],
                ['name' => 'admin.units.index',      'icon' => 'bi-rulers',       'label' => 'Units of Measure'],
                ['name' => 'admin.products.index',   'icon' => 'bi-box-seam',     'label' => 'Products / Parts'],
                ['name' => 'admin.batteries.index',  'icon' => 'bi-battery-charging', 'label' => 'Batteries'],
                ['name' => 'admin.lubricants.index', 'icon' => 'bi-droplet',      'label' => 'Lubricants'],
            ],
        ],
        [
            'title' => 'Warehousing',
            'items' => [
                ['name' => 'admin.warehouses.index',    'icon' => 'bi-building',     'label' => 'Warehouses'],
                ['name' => 'admin.bin-locations.index', 'icon' => 'bi-geo-alt',      'label' => 'Bin locations'],
            ],
        ],
        [
            'title' => 'Procurement',
            'items' => [
                ['name' => 'admin.purchase-orders.index',     'icon' => 'bi-receipt',     'label' => 'Purchase orders'],
                ['name' => 'admin.goods-receipts.index',      'icon' => 'bi-box-arrow-in-down', 'label' => 'Goods receipts'],
                ['name' => 'admin.suppliers.index',           'icon' => 'bi-truck',     'label' => 'Suppliers'],
                ['name' => 'admin.supplier-categories.index', 'icon' => 'bi-tags',      'label' => 'Supplier categories'],
            ],
        ],
        [
            'title' => 'Inventory Ops',
            'items' => [
                ['name' => 'admin.stock-adjustments.index',   'icon' => 'bi-sliders',     'label' => 'Stock adjustments'],
                ['name' => 'admin.stock-transfers.index',     'icon' => 'bi-arrow-left-right', 'label' => 'Stock transfers'],
                ['name' => 'admin.battery-stock-adjustments.index', 'icon' => 'bi-battery', 'label' => 'Battery adjustments'],
                ['name' => 'admin.lubricant-stock-adjustments.index', 'icon' => 'bi-droplet', 'label' => 'Lubricant adjustments'],
            ],
        ],
        [
            'title' => 'Access',
            'items' => [
                ['name' => 'admin.users.index',               'icon' => 'bi-people',      'label' => 'Users'],
                ['name' => 'admin.roles.index',               'icon' => 'bi-shield-lock', 'label' => 'Roles'],
                ['name' => 'admin.permissions.index',         'icon' => 'bi-key',         'label' => 'Permissions'],
            ],
        ],
        [
            'title' => 'Reports',
            'items' => [
                ['name' => 'admin.reports.valuation',   'icon' => 'bi-cash-coin',   'label' => 'Inventory valuation'],
                ['name' => 'admin.reports.low-stock',   'icon' => 'bi-exclamation-triangle', 'label' => 'Low stock'],
                ['name' => 'admin.reports.movements',   'icon' => 'bi-arrow-left-right',     'label' => 'Movement history'],
                ['name' => 'admin.reports.top-consumed','icon' => 'bi-bar-chart',   'label' => 'Top consumed'],
            ],
        ],
        [
            'title' => 'Operations',
            'items' => [
                ['name' => 'admin.departments.index', 'icon' => 'bi-diagram-3',  'label' => 'Departments'],
                ['name' => 'admin.equipment.index',   'icon' => 'bi-tools',      'label' => 'Equipment'],
                ['name' => 'admin.equipment-consumables.dashboard', 'icon' => 'bi-link-45deg', 'label' => 'Equipment consumables'],
                ['name' => 'admin.equipment-consumables.index',     'icon' => 'bi-list-ul',   'label' => 'Consumables list'],
            ],
        ],
        [
            'title' => 'Tooling',
            'items' => [
                ['name' => 'admin.tools.dashboard',     'icon' => 'bi-wrench-adjustable', 'label' => 'Tools dashboard'],
                ['name' => 'admin.tools.index',         'icon' => 'bi-tools',            'label' => 'Tools'],
                ['name' => 'admin.tool-categories.index','icon' => 'bi-tags',             'label' => 'Tool categories'],
            ],
        ],
        [
            'title' => 'System',
            'items' => [
                ['name' => 'admin.settings.edit',     'icon' => 'bi-sliders',     'label' => 'Settings'],
                ['name' => 'admin.audit-logs.index',  'icon' => 'bi-clock-history', 'label' => 'Audit logs'],
            ],
        ],
    ];

    $isActive = static fn (string $name) => str_starts_with($active ?? '', $name);
@endphp

<nav class="nav-section px-0">
    @foreach($groups as $group)
        <div class="nav-section-title">{{ $group['title'] }}</div>
        <ul class="list-unstyled mb-0">
            @foreach($group['items'] as $item)
                @php
                    $routeExists = \Illuminate\Support\Facades\Route::has($item['name']);
                @endphp
                @if($routeExists)
                    <li>
                        <a href="{{ route($item['name']) }}"
                           class="nav-link {{ $isActive($item['name']) ? 'active' : '' }}"
                           @if($isActive($item['name'])) aria-current="page" @endif
                           title="{{ $item['label'] }}">
                            <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    @endforeach
</nav>