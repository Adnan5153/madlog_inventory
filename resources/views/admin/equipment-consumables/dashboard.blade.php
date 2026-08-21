@extends('layouts.admin', ['title' => $title ?? 'Equipment consumables dashboard'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment consumables', 'url' => route('admin.equipment-consumables.index')],
        ['label' => 'Dashboard'],
    ]" />

    <x-admin.page-header title="Equipment consumables overview"
        subtitle="Track Parts, Batteries and Lubricants assigned to each piece of equipment — consumption, replacement schedule and live status.">
        <x-slot:actions>
            <a href="{{ route('admin.equipment-consumables.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list me-1"></i> All consumables
            </a>
            <a href="{{ route('admin.equipment-consumables.report.consumption') }}" class="btn btn-outline-secondary">
                <i class="bi bi-bar-chart me-1"></i> Consumption report
            </a>
            @can('create', App\Models\EquipmentConsumable::class)
                <a href="{{ route('admin.equipment-consumables.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Assign consumable
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-dashboard">
        {{-- KPI ROW --}}
        <div class="dashboard-grid row g-3">
            @foreach($kpis as $kpi)
                <div class="col-12 col-sm-6 col-xl">
                    <x-admin.dashboard.kpi-card
                        :title="$kpi['title']"
                        :value="($kpi['format'] ?? null) === 'currency'
                            ? '$' . number_format((float) $kpi['value'], 2)
                            : number_format((float) $kpi['value'], ($kpi['format'] ?? null) === 'currency' ? 2 : 0)"
                        :meta="$kpi['meta']"
                        :icon="$kpi['icon']"
                        :variant="$kpi['variant']"
                        :href="$kpi['href'] ?? null" />
                </div>
            @endforeach
        </div>

        {{-- ROW A: status distribution + attention --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-lg-7">
                <section class="admin-card" aria-labelledby="status-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="status-heading">Lifecycle distribution</h2>
                            <p class="dashboard-section-subtitle">Distribution across {{ $statusTotal }} assignment events.</p>
                        </div>
                        <div class="dashboard-section-actions">
                            <a href="{{ route('admin.equipment-consumables.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
                        </div>
                    </header>
                    <x-admin.dashboard.status-segmented-bar
                        :segments="$statusSegments"
                        :total="$statusTotal" />
                </section>
            </div>
            <div class="col-12 col-lg-5">
                <section class="admin-card" aria-labelledby="attention-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="attention-heading">Requires attention</h2>
                            <p class="dashboard-section-subtitle">Replacement schedule and recent activity.</p>
                        </div>
                    </header>
                    <div class="attention-list">
                        @forelse($attentionItems as $item)
                            <x-admin.dashboard.attention-item
                                :label="$item['label']"
                                :count="$item['count']"
                                :priority="$item['priority']"
                                :description="$item['description']"
                                :href="$item['href']" />
                        @empty
                            <x-admin.empty-state icon="bi-check-circle" title="All clear">
                                No replacement schedule exceptions at this time.
                            </x-admin.empty-state>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        {{-- ROW B: top consumed + recent activity --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-lg-5">
                <section class="admin-card" aria-labelledby="top-consumed-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="top-consumed-heading">Top consumed resources</h2>
                            <p class="dashboard-section-subtitle">Last 90 days, ranked by total cost.</p>
                        </div>
                    </header>
                    @if($topConsumed->isEmpty())
                        <x-admin.empty-state icon="bi-droplet" title="No consumption recorded">
                            Consumption events will appear here as consumables are used.
                        </x-admin.empty-state>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Resource</th>
                                        <th class="num">Qty</th>
                                        <th class="num">Events</th>
                                        <th class="num">Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topConsumed as $row)
                                        <tr>
                                            <td>
                                                <span class="eq-resource-pill eq-resource-pill--{{ \Illuminate\Support\Str::afterLast($row['type'], '\\') }}">
                                                    <i class="bi {{ \App\Models\EquipmentConsumable::resourceIcon($row['type']) }}" aria-hidden="true"></i>
                                                    {{ \App\Models\EquipmentConsumable::resourceLabel($row['type']) }}
                                                </span>
                                                <div class="small">{{ $row['name'] }}</div>
                                            </td>
                                            <td class="num">{{ number_format((float) $row['qty'], 2) }}</td>
                                            <td class="num">{{ $row['events'] }}</td>
                                            <td class="num">${{ number_format((float) $row['cost'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
            <div class="col-12 col-lg-7">
                <section class="admin-card" aria-labelledby="activity-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="activity-heading">Recent activity</h2>
                            <p class="dashboard-section-subtitle">Newest equipment consumable events first.</p>
                        </div>
                    </header>
                    @if($recentActivity->isEmpty())
                        <x-admin.empty-state icon="bi-clock-history" title="No activity yet">
                            Events will appear here as consumables are assigned, consumed, replaced or removed.
                        </x-admin.empty-state>
                    @else
                        <div class="activity-feed">
                            @foreach($recentActivity as $a)
                                <x-admin.dashboard.activity-item
                                    :event="$a['event']"
                                    :description="$a['description']"
                                    :actor="$a['actor']"
                                    :subject="$a['subject']"
                                    :subjectHref="$a['subjectHref']"
                                    :timestamp="$a['timestamp']"
                                    :icon="$a['icon']"
                                    :variant="$a['variant']" />
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>

        {{-- ROW C: recent consumables --}}
        <div class="row g-3">
            <div class="col-12">
                <section class="admin-card" aria-labelledby="recent-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="recent-heading">Recently assigned</h2>
                            <p class="dashboard-section-subtitle">Newest equipment consumable registrations.</p>
                        </div>
                    </header>
                    @if($recentConsumables->isEmpty())
                        <x-admin.empty-state icon="bi-link-45deg" title="No consumables yet">
                            @can('create', App\Models\EquipmentConsumable::class)
                                <a href="{{ route('admin.equipment-consumables.create') }}">Assign your first consumable</a>.
                            @else
                                Equipment consumables assigned in this workshop will appear here.
                            @endcan
                        </x-admin.empty-state>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Equipment</th>
                                        <th>Resource</th>
                                        <th>Status</th>
                                        <th>Assigned</th>
                                        <th>Replacement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentConsumables as $c)
                                        @php
                                            $resourceType = $c->resource_type;
                                            $r = $c->resource;
                                            $rname = $r ? ($r->name ?? $r->battery_code ?? $r->lubricant_code ?? '—') : '—';
                                            $current = $c->currentAssignment;
                                            $stat = $current?->status;
                                            $expected = $c->expected_replacement_at;
                                            $overdue = $expected && $expected->startOfDay()->lt(now()->startOfDay());
                                        @endphp
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.equipment-consumables.show', $c) }}" class="text-decoration-none">
                                                    {{ $c->equipment?->name ?? '—' }}
                                                </a>
                                                <div class="text-muted small">{{ $c->equipment?->asset_number ?? '' }}</div>
                                            </td>
                                            <td>
                                                <span class="eq-resource-pill eq-resource-pill--{{ \Illuminate\Support\Str::afterLast($resourceType, '\\') }}">
                                                    <i class="bi {{ \App\Models\EquipmentConsumable::resourceIcon($resourceType) }}" aria-hidden="true"></i>
                                                    {{ \App\Models\EquipmentConsumable::resourceLabel($resourceType) }}
                                                </span>
                                                <div class="small">{{ $rname }}</div>
                                            </td>
                                            <td>
                                                @if($stat)
                                                    <span class="admin-status-badge is-{{ $stat->color() }}">{{ $stat->label() }}</span>
                                                @else
                                                    <span class="text-muted small">Closed</span>
                                                @endif
                                            </td>
                                            <td>{{ $c->assigned_at?->format('Y-m-d') ?? '—' }}</td>
                                            <td>
                                                @if($expected)
                                                    <span class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">
                                                        {{ $expected->format('Y-m-d') }}
                                                        @if($overdue)<i class="bi bi-exclamation-triangle-fill ms-1" aria-hidden="true"></i>@endif
                                                    </span>
                                                @else —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection