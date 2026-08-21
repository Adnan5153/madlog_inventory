@extends('layouts.admin', ['title' => $title ?? 'Consumption report'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment consumables', 'url' => route('admin.equipment-consumables.index')],
        ['label' => 'Consumption report'],
    ]" />

    <x-admin.page-header title="Consumption report"
        subtitle="Equipment consumables consumed over the selected window.">
        <x-slot:actions>
            <a href="{{ route('admin.equipment-consumables.dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
            <a href="{{ route('admin.equipment-consumables.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list me-1"></i> All consumables
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.equipment-consumables.report.consumption') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-3">
                <label for="days" class="form-label">Window</label>
                <select id="days" name="days" class="form-select" onchange="this.form.submit()">
                    @foreach([7, 30, 90, 180, 365] as $opt)
                        <option value="{{ $opt }}" @selected($days === $opt)>Last {{ $opt }} days</option>
                    @endforeach
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.equipment-consumables.report.consumption')" />
        </form>
    </x-admin.filter-bar>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="admin-card kpi-card">
                <div class="kpi-card__icon is-primary"><i class="bi bi-droplet"></i></div>
                <div>
                    <div class="kpi-card__title">Events</div>
                    <div class="kpi-card__value num">{{ number_format($events->count()) }}</div>
                    <div class="kpi-card__meta">In the last {{ $days }} days</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="admin-card kpi-card">
                <div class="kpi-card__icon is-info"><i class="bi bi-stack"></i></div>
                <div>
                    <div class="kpi-card__title">Total quantity</div>
                    <div class="kpi-card__value num">{{ number_format($totalQty, 3) }}</div>
                    <div class="kpi-card__meta">Across all events</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="admin-card kpi-card">
                <div class="kpi-card__icon is-warning"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="kpi-card__title">Total cost</div>
                    <div class="kpi-card__value num">${{ number_format($totalCost, 2) }}</div>
                    <div class="kpi-card__meta">Sum of total_cost</div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <h2 class="h6 mb-3">Events</h2>
        @if($events->isEmpty())
            <x-admin.empty-state icon="bi-droplet" title="No consumption events">
                No consumables have been consumed in this window.
            </x-admin.empty-state>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Performed at</th>
                            <th>Equipment</th>
                            <th>Resource</th>
                            <th class="num">Qty</th>
                            <th class="num">Cost</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($events as $e)
                            @php
                                $resourceType = $e->equipmentConsumable?->resource_type;
                                $r = $e->equipmentConsumable?->resource;
                                $rname = $r ? ($r->name ?? $r->battery_code ?? $r->lubricant_code ?? '—') : '—';
                                $equipment = $e->equipmentConsumable?->equipment;
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $e->performed_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($equipment)
                                        <a href="{{ route('admin.equipment-consumables.show', $e->equipmentConsumable) }}" class="text-decoration-none">
                                            {{ $equipment->name }}
                                        </a>
                                        <div class="text-muted small">{{ $equipment->asset_number ?? '' }}</div>
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    <span class="eq-resource-pill eq-resource-pill--{{ \Illuminate\Support\Str::afterLast($resourceType, '\\') }}">
                                        <i class="bi {{ \App\Models\EquipmentConsumable::resourceIcon($resourceType) }}" aria-hidden="true"></i>
                                        {{ \App\Models\EquipmentConsumable::resourceLabel($resourceType) }}
                                    </span>
                                    <div class="small">{{ $rname }}</div>
                                </td>
                                <td class="num">{{ number_format((float) $e->quantity, 3) }}</td>
                                <td class="num">${{ number_format((float) ($e->total_cost ?? 0), 2) }}</td>
                                <td>{{ $e->performedBy?->name ?? 'System' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection