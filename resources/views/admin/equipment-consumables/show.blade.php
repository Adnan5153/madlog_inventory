@php
    use App\Enums\EquipmentConsumableStatus;
    use App\Enums\EquipmentConsumableType;

    $consumable = $consumable ?? null;
    $equipment = $consumable?->equipment;
    $resource = $consumable?->resource;
    $resourceType = $consumable?->resource_type;
    $current = $consumable?->currentAssignment;
    $currentStatus = $current?->status instanceof EquipmentConsumableStatus ? $current->status : null;
    $isOpen = $current !== null;
    $resourceTypeLabel = $resourceType ? \App\Models\EquipmentConsumable::resourceLabel($resourceType) : 'Resource';
    $resourceTypeIcon = $resourceType ? \App\Models\EquipmentConsumable::resourceIcon($resourceType) : 'bi-link-45deg';
    $resourceName = $resource ? ($resource->name ?? $resource->battery_code ?? $resource->lubricant_code ?? 'Resource') : 'Resource #'.$consumable?->resource_id;
    $resourceCode = $resource ? ($resource->sku ?? $resource->battery_code ?? $resource->lubricant_code ?? null) : null;
    $totalCost = (float) $consumable->assignments->where('status', '!=', EquipmentConsumableStatus::Cancelled->value)->sum('total_cost');
    $totalQty = (float) $consumable->assignments->where('type', EquipmentConsumableType::Consumed->value)->sum('quantity');
    $expected = $consumable?->expected_replacement_at;
    $expectedDays = $expected ? (int) now()->startOfDay()->diffInDays($expected->startOfDay(), false) : null;
@endphp

@extends('layouts.admin', ['title' => $equipment?->name ?? 'Consumable'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment consumables', 'url' => route('admin.equipment-consumables.index')],
        ['label' => $equipment?->name ?? 'Consumable'],
    ]" />

    <x-admin.page-header
        :title="$equipment?->name ?? 'Consumable'"
        :subtitle="$resourceTypeLabel . ' · ' . $resourceName . ($resourceCode ? ' · ' . $resourceCode : '')">
        <x-slot:actions>
            <a href="{{ route('admin.equipment-consumables.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            @can('update', $consumable)
                <a href="{{ route('admin.equipment-consumables.edit', $consumable) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    {{-- KPI strip --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="admin-card kpi-card">
                <div class="kpi-card__icon is-info"><i class="bi {{ $resourceTypeIcon }}" aria-hidden="true"></i></div>
                <div>
                    <div class="kpi-card__title">Resource</div>
                    <div class="kpi-card__value">{{ $resourceTypeLabel }}</div>
                    <div class="kpi-card__meta text-truncate">{{ $resourceName }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card kpi-card">
                <div class="kpi-card__icon is-{{ $currentStatus?->color() ?? 'secondary' }}">
                    <i class="bi {{ $currentStatus === EquipmentConsumableStatus::Assigned ? 'bi-link-45deg' : 'bi-tools' }}" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="kpi-card__title">Status</div>
                    <div class="kpi-card__value">{{ $currentStatus?->label() ?? 'Closed' }}</div>
                    <div class="kpi-card__meta">@if($isOpen) Open assignment @else All events closed @endif</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card kpi-card">
                <div class="kpi-card__icon is-warning"><i class="bi bi-droplet" aria-hidden="true"></i></div>
                <div>
                    <div class="kpi-card__title">Consumed</div>
                    <div class="kpi-card__value">{{ number_format($totalQty, 3) }}</div>
                    <div class="kpi-card__meta">Across {{ $consumable->assignments->where('type', EquipmentConsumableType::Consumed->value)->count() }} events</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="admin-card kpi-card">
                <div class="kpi-card__icon is-{{ $expectedDays !== null && $expectedDays < 0 ? 'danger' : ($expectedDays !== null && $expectedDays <= 7 ? 'warning' : 'success') }}">
                    <i class="bi bi-alarm" aria-hidden="true"></i>
                </div>
                <div>
                    <div class="kpi-card__title">Replacement</div>
                    <div class="kpi-card__value">{{ $expected?->format('Y-m-d') ?? '—' }}</div>
                    <div class="kpi-card__meta">
                        @if($expectedDays === null) Not scheduled
                        @elseif($expectedDays < 0) {{ abs($expectedDays) }} days overdue
                        @elseif($expectedDays === 0) Today
                        @else In {{ $expectedDays }} days
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Equipment</dt>
                    <dd class="col-7">
                        <a href="{{ $equipment ? route('admin.equipment.show', $equipment) : '#' }}" class="text-decoration-none">
                            {{ $equipment?->name ?? '—' }}
                        </a>
                    </dd>
                    <dt class="col-5 text-muted">Asset #</dt><dd class="col-7">{{ $equipment?->asset_number ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Resource type</dt><dd class="col-7">{{ $resourceTypeLabel }}</dd>
                    <dt class="col-5 text-muted">Resource</dt><dd class="col-7">{{ $resourceName }}</dd>
                    @if($resourceCode)
                        <dt class="col-5 text-muted">Code</dt><dd class="col-7">{{ $resourceCode }}</dd>
                    @endif
                    <dt class="col-5 text-muted">Workshop</dt><dd class="col-7">{{ $consumable?->workshop?->name ?? '—' }}</dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Lifecycle</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Assigned at</dt><dd class="col-7">{{ $consumable?->assigned_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Replacement due</dt><dd class="col-7">{{ $expected?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Total cost</dt><dd class="col-7 num">${{ number_format($totalCost, 2) }}</dd>
                    <dt class="col-5 text-muted">Notes</dt><dd class="col-7">{{ $consumable?->notes ?? '—' }}</dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Actions</h2>
                <div class="d-grid gap-2">
                    @if($isOpen)
                        @can('consume', $consumable)
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#consumeModal">
                                <i class="bi bi-droplet me-1"></i> Record consumption
                            </button>
                        @endcan
                        @can('replace', $consumable)
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#replaceModal">
                                <i class="bi bi-arrow-left-right me-1"></i> Replace consumable
                            </button>
                        @endcan
                        @can('remove', $consumable)
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#removeModal">
                                <i class="bi bi-x-circle me-1"></i> Remove consumable
                            </button>
                        @endcan
                    @else
                        <p class="text-muted small mb-0">
                            This consumable is closed. To re-track a new resource against this equipment,
                            assign a fresh consumable from the index.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="admin-card">
                <h2 class="h6 mb-3">Assignment timeline</h2>
                @if($consumable->assignments->isEmpty())
                    <x-admin.empty-state icon="bi-clock-history" title="No assignments recorded">
                        Once you record an event, it will appear here.
                    </x-admin.empty-state>
                @else
                    <div class="eq-consumable-timeline">
                        @foreach($consumable->assignments as $assignment)
                            @php
                                $type = $assignment->type instanceof EquipmentConsumableType ? $assignment->type : null;
                                $status = $assignment->status instanceof EquipmentConsumableStatus ? $assignment->status : null;
                                $icon = $type?->icon() ?? 'bi-activity';
                                $color = $status?->color() ?? $type?->color() ?? 'secondary';
                            @endphp
                            <div class="eq-consumable-step">
                                <div class="eq-consumable-step__icon is-{{ $color }}"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
                                <div class="eq-consumable-step__body">
                                    <div class="eq-consumable-step__head">
                                        <span class="eq-consumable-step__title">{{ $type?->label() ?? 'Event' }}</span>
                                        @if($status)
                                            <span class="admin-status-badge is-{{ $status->color() }}">{{ $status->label() }}</span>
                                        @endif
                                        <span class="eq-consumable-step__time text-muted">{{ $assignment->performed_at?->format('Y-m-d H:i') }}</span>
                                    </div>
                                    <div class="eq-consumable-step__meta">
                                        <span>Qty: <span class="num">{{ number_format((float) $assignment->quantity, 3) }}</span></span>
                                        @if($assignment->unit)
                                            <span>· {{ $assignment->unit->name }}@if($assignment->unit->short_code)({{ $assignment->unit->short_code }})@endif</span>
                                        @endif
                                        @if($assignment->total_cost !== null)
                                            <span>· Cost: <span class="num">${{ number_format((float) $assignment->total_cost, 2) }}</span></span>
                                        @endif
                                        @if($assignment->bin)
                                            <span>· Bin: {{ $assignment->bin->code }}</span>
                                        @endif
                                    </div>
                                    <div class="eq-consumable-step__footer">
                                        By {{ $assignment->performedBy?->name ?? 'System' }}
                                        @if($assignment->previousAssignment)
                                            · Replaces assignment #{{ $assignment->previous_assignment_id }}
                                        @endif
                                        @if($assignment->stock_movement_type && $assignment->stock_movement_id)
                                            · Ledger: {{ $assignment->stock_movement_type }} #{{ $assignment->stock_movement_id }}
                                        @endif
                                    </div>
                                    @if($assignment->notes)
                                        <div class="eq-consumable-step__notes">{{ $assignment->notes }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Action modals --}}
    @include('admin.equipment-consumables._modals', [
        'consumable' => $consumable,
        'parts' => $parts,
        'batteries' => $batteries,
        'lubricants' => $lubricants,
        'units' => $units,
        'bins' => $bins,
    ])
@endsection