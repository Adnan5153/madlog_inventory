@extends('layouts.admin', ['title' => 'Dashboard'])

@section('content')
    <x-admin.page-header
        title="Dashboard"
        :subtitle="'Welcome, '.($user?->name ?? 'admin').'. Here is the state of your inventory.'">
        <x-slot:actions>
            @if (Route::has('admin.products.index'))
                <a href="{{ route('admin.products.index') }}" class="btn btn-warning">
                    <i class="bi bi-box-seam me-1"></i> Products
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

    <div class="row g-3">
        <div class="col-12">
            <div class="admin-card">
                <h2 class="h6 mb-3"><i class="bi bi-clock-history me-1"></i> Recent activity</h2>
                <div class="admin-table">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentActivity as $log)
                                <tr>
                                    <td class="small text-muted">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                                    <td><x-admin.status-badge variant="info">{{ $log->action }}</x-admin.status-badge></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2"><x-admin.empty-state icon="bi-clock-history" title="No activity yet" /></td>
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
    {{-- Chart.js global registered Chart + tree-shaken controllers via admin.js. --}}
    <script>
        (function () {
            'use strict';

            // Bar chart: top-N most-consumed parts.
            const barLabels = @json($charts['topConsumed']['labels'] ?? []);
            const barValues = @json($charts['topConsumed']['values'] ?? []);

            if (barLabels.length && window.Chart) {
                const canvas = document.getElementById('chart-top-consumed');
                if (canvas) {
                    new Chart(canvas, {
                        type: 'bar',
                        data: {
                            labels: barLabels,
                            datasets: [{
                                label: 'Units consumed',
                                data: barValues,
                                backgroundColor: '#0d6efd',
                                borderRadius: 4,
                                maxBarThickness: 32,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => `${ctx.parsed.x} units`,
                                    },
                                },
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: { precision: 0 },
                                },
                            },
                        },
                    });
                }
            }

            // Doughnut chart: inventory value by category.
            const pieLabels = @json($charts['inventoryByCat']['labels'] ?? []);
            const pieValues = @json($charts['inventoryByCat']['values'] ?? []);
            const pieTotal  = @json($charts['inventoryByCat']['total']  ?? 0);

            if (pieLabels.length && window.Chart) {
                const canvas = document.getElementById('chart-inventory-by-category');
                if (canvas) {
                    const palette = [
                        '#0d6efd', '#198754', '#dc3545', '#ffc107',
                        '#6f42c1', '#fd7e14', '#20c997', '#0dcaf0',
                    ];
                    const colors = pieLabels.map((_, i) => palette[i % palette.length]);

                    new Chart(canvas, {
                        type: 'doughnut',
                        data: {
                            labels: pieLabels,
                            datasets: [{
                                data: pieValues,
                                backgroundColor: colors,
                                borderColor: '#fff',
                                borderWidth: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { boxWidth: 12, padding: 8 },
                                },
                                tooltip: {
                                    callbacks: {
                                        label: (ctx) => {
                                            const value = Number(ctx.parsed) || 0;
                                            const pct = pieTotal > 0
                                                ? ((value / pieTotal) * 100).toFixed(1)
                                                : '0.0';
                                            return `${ctx.label}: ${value.toFixed(2)} (${pct}%)`;
                                        },
                                    },
                                },
                            },
                            cutout: '55%',
                        },
                    });
                }
            }
        })();
    </script>
@endpush
