@php
    use App\Enums\EquipmentConsumableStatus;
    use App\Enums\EquipmentConsumableType;

    $equipment = $equipment ?? null;
    if (! $equipment) {
        return;
    }

    $consumables = \App\Models\EquipmentConsumable::query()
        ->with(['resource', 'currentAssignment.performedBy:id,name'])
        ->where('equipment_id', $equipment->id)
        ->latest('assigned_at')
        ->get();

    $activeCount = $consumables->filter(fn ($c) => $c->currentAssignment !== null)->count();
    $totalCost = (float) $consumables->sum(fn ($c) => (float) $c->assignments()->where('status', '!=', EquipmentConsumableStatus::Cancelled->value)->sum('total_cost'));
    $consumedQty = (float) $consumables->sum(fn ($c) => (float) $c->assignments()->where('type', EquipmentConsumableType::Consumed->value)->sum('quantity'));
@endphp

<div class="admin-card">
    <header class="dashboard-section-title">
        <div>
            <h2 class="h6 text-muted text-uppercase mb-1">Resources</h2>
            <p class="dashboard-section-subtitle mb-0">Parts, batteries and lubricants assigned to this equipment.</p>
        </div>
        <div class="dashboard-section-actions">
            <a href="{{ route('admin.equipment.equipment-consumables.index', $equipment) }}" class="btn btn-sm btn-outline-secondary">
                View all
            </a>
            @can('create', App\Models\EquipmentConsumable::class)
                <a href="{{ route('admin.equipment-consumables.create', ['equipment_id' => $equipment->id]) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Assign
                </a>
            @endcan
        </div>
    </header>

    <div class="row g-2 mt-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-card--inline">
                <div class="kpi-card__icon is-primary"><i class="bi bi-link-45deg"></i></div>
                <div>
                    <div class="kpi-card__title">Active</div>
                    <div class="kpi-card__value num">{{ $activeCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-card--inline">
                <div class="kpi-card__icon is-info"><i class="bi bi-stack"></i></div>
                <div>
                    <div class="kpi-card__title">Tracked</div>
                    <div class="kpi-card__value num">{{ $consumables->count() }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-card--inline">
                <div class="kpi-card__icon is-warning"><i class="bi bi-droplet"></i></div>
                <div>
                    <div class="kpi-card__title">Consumed</div>
                    <div class="kpi-card__value num">{{ number_format($consumedQty, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="kpi-card kpi-card--inline">
                <div class="kpi-card__icon is-success"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="kpi-card__title">Lifetime cost</div>
                    <div class="kpi-card__value num">${{ number_format($totalCost, 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($consumables->isEmpty())
        <x-admin.empty-state icon="bi-link-45deg" title="No consumables tracked">
            Nothing has been assigned to this equipment yet.
        </x-admin.empty-state>
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Resource</th>
                        <th>Status</th>
                        <th class="num">Qty</th>
                        <th>Replacement</th>
                        <th>Assigned</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($consumables->take(8) as $c)
                        @php
                            $resource = $c->resource;
                            $resourceType = $c->resource_type;
                            $current = $c->currentAssignment;
                            $status = $current?->status;
                            $resourceName = $resource ? ($resource->name ?? $resource->battery_code ?? $resource->lubricant_code ?? '—') : '—';
                            $expected = $c->expected_replacement_at;
                            $overdue = $expected && $expected->startOfDay()->lt(now()->startOfDay());
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('admin.equipment-consumables.show', $c) }}" class="text-decoration-none">
                                    <span class="eq-resource-pill eq-resource-pill--{{ \Illuminate\Support\Str::afterLast($resourceType, '\\') }}">
                                        <i class="bi {{ \App\Models\EquipmentConsumable::resourceIcon($resourceType) }}" aria-hidden="true"></i>
                                        {{ \App\Models\EquipmentConsumable::resourceLabel($resourceType) }}
                                    </span>
                                    {{ $resourceName }}
                                </a>
                            </td>
                            <td>
                                @if($status)
                                    <span class="admin-status-badge is-{{ $status->color() }}">{{ $status->label() }}</span>
                                @else
                                    <span class="text-muted small">Closed</span>
                                @endif
                            </td>
                            <td class="num">{{ $current ? number_format((float) $current->quantity, 3) : '—' }}</td>
                            <td>
                                @if($expected)
                                    <span class="{{ $overdue ? 'text-danger fw-semibold' : '' }}">
                                        {{ $expected->format('Y-m-d') }}
                                        @if($overdue)<i class="bi bi-exclamation-triangle-fill ms-1" aria-hidden="true"></i>@endif
                                    </span>
                                @else —
                                @endif
                            </td>
                            <td class="text-muted small">{{ $c->assigned_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($consumables->count() > 8)
            <div class="mt-2">
                <a href="{{ route('admin.equipment.equipment-consumables.index', $equipment) }}" class="small">
                    View all {{ $consumables->count() }} consumables →
                </a>
            </div>
        @endif
    @endif
</div>