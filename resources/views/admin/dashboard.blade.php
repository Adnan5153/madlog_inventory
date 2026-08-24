@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
    <x-admin.page-header
        title="Dashboard"
        :subtitle="'Welcome, '.($user?->name ?? 'admin').'. Here is the state of your inventory.'">
        <x-slot:actions>
            @if (Route::has('admin.products.index'))
                <a href="{{ route('admin.products.index') }}" class="btn btn-primary">
                    <i class="bi bi-box-seam me-1"></i> Browse products
                </a>
            @endif
            <a href="{{ route('admin.settings.edit') }}" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i> Settings
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <x-admin.stat-card
                label="Workshops"
                :value="number_format($totals['workshops'])"
                icon="bi-building"
                variant="info" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-admin.stat-card
                label="Parts catalog"
                :value="number_format($totals['parts'])"
                icon="bi-box-seam" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-admin.stat-card
                label="Active units"
                :value="number_format($totals['units'])"
                icon="bi-rulers"
                variant="success" />
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <x-admin.stat-card
                label="Active equipment"
                :value="number_format($totals['equipment'])"
                icon="bi-tools"
                variant="success" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Categories"
                :value="number_format($totals['categories'] ?? 0)"
                icon="bi-tags" />
        </div>
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Suppliers"
                :value="number_format($totals['suppliers'])"
                icon="bi-truck" />
        </div>
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Departments"
                :value="number_format($totals['departments'] ?? 0)"
                icon="bi-diagram-3" />
        </div>
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Low-stock parts"
                :value="number_format($lowStockCount)"
                icon="bi-exclamation-triangle"
                :variant="$lowStockCount > 0 ? 'danger' : 'success'" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Tools"
                :value="number_format($totals['tools'] ?? 0)"
                icon="bi-wrench-adjustable"
                :href="route('admin.tools.index')" />
        </div>
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Tools checked out"
                :value="number_format($totals['tools_checked_out'] ?? 0)"
                icon="bi-box-arrow-up-right"
                variant="primary" />
        </div>
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Tools under maintenance"
                :value="number_format($totals['tools_under_maintenance'] ?? 0)"
                icon="bi-wrench"
                variant="warning" />
        </div>
        <div class="col-12 col-md-3">
            <x-admin.stat-card
                label="Overdue tool checkouts"
                :value="number_format($totals['tools_overdue_checkouts'] ?? 0)"
                icon="bi-clock-history"
                :variant="($totals['tools_overdue_checkouts'] ?? 0) > 0 ? 'danger' : 'success'" />
        </div>
    </div>

    {{-- 5th KPI row: Total inventory value (single tile spanning the full width on small screens). --}}
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-4">
            <x-admin.stat-card
                label="Total inventory value"
                :value="'$' . number_format((float) ($valuation['inventory_value'] ?? 0), 2)"
                :meta="number_format((int) ($valuation['items_count'] ?? 0)) . ' inventory buckets'"
                icon="bi-cash-stack"
                variant="primary" />
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-7">
            <x-admin.chart-card
                title="Top 10 most-consumed parts (last 30 days)"
                icon="bi-bar-chart-line"
                :height="280"
                :hasData="!empty($charts['topConsumed']['labels'])"
                emptyMessage="No outgoing stock movements recorded in the last 30 days.">
                <canvas id="chart-top-consumed"
                        aria-label="Top consumed parts bar chart"
                        role="img"></canvas>
            </x-admin.chart-card>
        </div>
        <div class="col-12 col-lg-5">
            <x-admin.chart-card
                title="Inventory value by category"
                icon="bi-pie-chart"
                :height="280"
                :hasData="!empty($charts['inventoryByCat']['labels'])"
                emptyMessage="No inventory values yet — record a goods receipt to populate this chart.">
                <canvas id="chart-inventory-by-category"
                        aria-label="Inventory value by category pie chart"
                        role="img"></canvas>
            </x-admin.chart-card>
        </div>
    </div>

    <div class="row g-3 mb-4 dashboard-analytics-section">
        <div class="col-12 col-lg-6">
            <x-admin.chart-card
                title="Monthly stock movement (last 12 months)"
                icon="bi-bar-chart-steps"
                :height="280"
                :hasData="!empty($charts['monthlyMovements']['labels'])"
                emptyMessage="No stock movements recorded in the last 12 months.">
                <canvas id="chart-monthly-movements"
                        aria-label="Monthly stock movement bar chart"
                        role="img"></canvas>
            </x-admin.chart-card>
        </div>
        <div class="col-12 col-lg-6">
            <x-admin.chart-card
                title="Inventory movement trend"
                icon="bi-graph-up"
                :height="280"
                :hasData="!empty($charts['movementTrend']['labels'])"
                emptyMessage="No stock movements recorded in the last 12 months.">
                <canvas id="chart-movement-trend"
                        aria-label="Inventory movement trend line chart"
                        role="img"></canvas>
            </x-admin.chart-card>
        </div>
    </div>

    <div class="row g-3 mb-4 dashboard-analytics-section">
        <div class="col-12 col-lg-5">
            <x-admin.chart-card
                title="Inventory quantity by category"
                icon="bi-pie-chart"
                :height="280"
                :hasData="!empty($charts['quantityByCategory']['labels'])"
                emptyMessage="No part inventory recorded yet.">
                <canvas id="chart-quantity-by-category"
                        aria-label="Inventory quantity by category pie chart"
                        role="img"></canvas>
            </x-admin.chart-card>
        </div>
        <div class="col-12 col-lg-7">
            <x-admin.chart-card
                title="Stock value by category (top 10)"
                icon="bi-bar-chart"
                :height="280"
                :hasData="!empty($charts['stockValueByCat']['labels'])"
                emptyMessage="No inventory value recorded yet — record a goods receipt to populate this chart.">
                <canvas id="chart-stock-value-by-category"
                        aria-label="Stock value by category bar chart"
                        role="img"></canvas>
            </x-admin.chart-card>
        </div>
    </div>

    @php
        $hasStandalone = !empty($charts['batteries']) || !empty($charts['lubricants']) || !empty($charts['tools']);
    @endphp

    <div class="row g-3 mb-4 dashboard-analytics-section">
        @if(!empty($charts['batteries']))
            <div class="col-12 col-md-4">
                <x-admin.chart-card
                    title="Batteries — quantity by type"
                    icon="bi-battery-charging"
                    :height="260"
                    :hasData="true">
                    <canvas id="chart-batteries"
                            aria-label="Batteries quantity by type doughnut chart"
                            role="img"></canvas>
                </x-admin.chart-card>
            </div>
        @endif
        @if(!empty($charts['lubricants']))
            <div class="col-12 col-md-4">
                <x-admin.chart-card
                    title="Lubricants — quantity by type"
                    icon="bi-droplet-half"
                    :height="260"
                    :hasData="true">
                    <canvas id="chart-lubricants"
                            aria-label="Lubricants quantity by type doughnut chart"
                            role="img"></canvas>
                </x-admin.chart-card>
            </div>
        @endif
        @if(!empty($charts['tools']))
            <div class="col-12 col-md-4">
                <x-admin.chart-card
                    title="Tools — count by category"
                    icon="bi-wrench-adjustable-circle"
                    :height="260"
                    :hasData="true">
                    <canvas id="chart-tools"
                            aria-label="Tools count by category doughnut chart"
                            role="img"></canvas>
                </x-admin.chart-card>
            </div>
        @endif
        @unless($hasStandalone)
            <div class="col-12">
                <div class="admin-card">
                    <x-admin.empty-state
                        icon="bi-boxes"
                        title="No Batteries, Lubricants, or Tools tracked in this workshop yet" />
                </div>
            </div>
        @endunless
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="admin-card">
                <h2 class="h6 mb-3"><i class="bi bi-arrow-down-up me-1"></i> Recent stock movements</h2>
                <div class="admin-table table-responsive">
                    <table class="table table-sm align-middle mb-0 recent-movements-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>SKU / part no.</th>
                                <th>Type</th>
                                <th class="text-end">Quantity</th>
                                <th>Reference</th>
                                <th>Performed by</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentMovements as $row)
                                <tr>
                                    <td class="small text-muted num">{{ $row['date']->format('Y-m-d H:i') }}</td>
                                    <td>{{ $row['product_name'] }}</td>
                                    <td><code>{{ $row['sku'] ?? '—' }}</code></td>
                                    <td>
                                        <x-admin.status-badge :variant="$row['direction'] === 'in' ? 'success' : 'danger'">
                                            {{ ucfirst(str_replace('_', ' ', $row['type'])) }}
                                            <i class="bi {{ $row['direction'] === 'in' ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }} ms-1"></i>
                                        </x-admin.status-badge>
                                    </td>
                                    <td class="text-end num {{ $row['direction'] === 'in' ? 'is-in' : 'is-out' }}">
                                        {{ ($row['direction'] === 'in' ? '+' : '−') . number_format(abs($row['quantity']), 2) }}
                                    </td>
                                    <td>
                                        @if(!empty($row['reference_type']))
                                            <span class="small text-muted">{{ class_basename($row['reference_type']) }}</span>
                                            <span class="num">#{{ $row['reference_id'] }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['user_name'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <x-admin.empty-state icon="bi-inboxes" title="No stock movements recorded yet" />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- Expose chart data to the consolidated app.js bundle, which owns the
         Chart.js rendering logic. We just hand it a JSON-shaped object on
         `window.__dashboardCharts` and let app.js pick it up. --}}
    <script>
        window.__dashboardCharts = @json($charts ?? []);
    </script>
@endpush
