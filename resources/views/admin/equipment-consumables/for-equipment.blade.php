@extends('layouts.admin', ['title' => $title ?? 'Equipment consumables'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment', 'url' => route('admin.equipment.index')],
        ['label' => $equipment->name, 'url' => route('admin.equipment.show', $equipment)],
        ['label' => 'Consumables'],
    ]" />

    <x-admin.page-header
        :title="$equipment->name"
        :subtitle="'Consumables tracked against this equipment · ' . ($equipment->asset_number ?? '')">
        <x-slot:actions>
            <a href="{{ route('admin.equipment.show', $equipment) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to equipment
            </a>
            @can('create', App\Models\EquipmentConsumable::class)
                <a href="{{ route('admin.equipment-consumables.create', ['equipment_id' => $equipment->id]) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Assign consumable
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    @if($consumables->isEmpty())
        <x-admin.empty-state icon="bi-link-45deg" title="No consumables assigned">
            Nothing has been tracked against this equipment yet.
        </x-admin.empty-state>
    @else
        <div class="row g-3">
            @foreach($consumables as $c)
                @php
                    $resource = $c->resource;
                    $resourceType = $c->resource_type;
                    $current = $c->currentAssignment;
                    $status = $current?->status;
                    $resourceName = $resource ? ($resource->name ?? $resource->battery_code ?? $resource->lubricant_code ?? '—') : '—';
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('admin.equipment-consumables.show', $c) }}" class="admin-card eq-consumable-card text-decoration-none text-reset">
                        <div class="d-flex align-items-start gap-2">
                            <span class="eq-resource-pill eq-resource-pill--{{ \Illuminate\Support\Str::afterLast($resourceType, '\\') }}">
                                <i class="bi {{ \App\Models\EquipmentConsumable::resourceIcon($resourceType) }}" aria-hidden="true"></i>
                                {{ \App\Models\EquipmentConsumable::resourceLabel($resourceType) }}
                            </span>
                            @if($status)
                                <span class="admin-status-badge is-{{ $status->color() }} ms-auto">{{ $status->label() }}</span>
                            @endif
                        </div>
                        <div class="fw-semibold mt-2">{{ $resourceName }}</div>
                        <div class="text-muted small">{{ $c->assignments->count() }} events · qty {{ $current ? number_format((float) $current->quantity, 3) : '—' }}</div>
                        <div class="d-flex justify-content-between mt-2 text-muted small">
                            <span>Assigned {{ $c->assigned_at?->format('Y-m-d') ?? '—' }}</span>
                            <span>Repl {{ $c->expected_replacement_at?->format('Y-m-d') ?? '—' }}</span>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
@endsection