@extends('layouts.admin', ['title' => 'Equipment details'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment', 'url' => route('admin.equipment.index')],
        ['label' => $equipment->name],
    ]" />

    <x-admin.page-header :title="$equipment->name" :subtitle="'Asset #' . ($equipment->asset_number ?? '—') . ' · ID #' . $equipment->id">
        <x-slot:actions>
            <a href="{{ route('admin.equipment.edit', $equipment) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="admin-card">
                <h2 class="h6 text-muted text-uppercase">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Manufacturer</dt><dd class="col-sm-8">{{ $equipment->manufacturer ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Model</dt>     <dd class="col-sm-8">{{ $equipment->model ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Serial #</dt>  <dd class="col-sm-8">{{ $equipment->serial_number ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Type</dt>      <dd class="col-sm-8">{{ $equipment->equipment_type ?? '—' }}</dd>
                </dl>
            </div>
        </div>
        <div class="col-md-6">
            <div class="admin-card">
                <h2 class="h6 text-muted text-uppercase">Ownership</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Department</dt>
                    <dd class="col-sm-8">{{ $equipment->department?->name ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Storage bin</dt>
                    <dd class="col-sm-8">{{ $equipment->binLocation?->code ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Status</dt>
                    <dd class="col-sm-8">
                        <x-admin.status-badge :variant="match($equipment->status) {
                            'active' => 'success',
                            'maintenance' => 'warning',
                            default => 'danger',
                        }">{{ ucfirst($equipment->status) }}</x-admin.status-badge>
                    </dd>
                </dl>
            </div>
        </div>
        <div class="col-md-6">
            <div class="admin-card">
                <h2 class="h6 text-muted text-uppercase">Lifecycle</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Purchased</dt>
                    <dd class="col-sm-8">{{ optional($equipment->purchase_date)->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Warranty</dt>
                    <dd class="col-sm-8">{{ optional($equipment->warranty_expires_at)->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Active</dt>
                    <dd class="col-sm-8">{{ $equipment->is_active ? 'Yes' : 'No' }}</dd>
                </dl>
            </div>
        </div>
        @if($equipment->notes)
            <div class="col-12">
                <div class="admin-card">
                    <h2 class="h6 text-muted text-uppercase">Notes</h2>
                    <p class="mb-0">{{ $equipment->notes }}</p>
                </div>
            </div>
        @endif
    </div>
@endsection