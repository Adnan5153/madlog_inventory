@extends('layouts.admin', ['title' => $warehouse->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Warehouses', 'url' => route('admin.warehouses.index')],
        ['label' => $warehouse->name],
    ]" />

    <x-admin.page-header :title="$warehouse->name" :subtitle="$warehouse->slug ?? 'No slug'">
        <x-slot:actions>
            @if($user?->isAdmin() && $user?->workshop_id === null)
                <a href="{{ route('admin.warehouses.edit', $warehouse) }}" class="btn btn-warning">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Name</dt><dd class="col-8">{{ $warehouse->name }}</dd>
                    <dt class="col-4 text-muted">Slug</dt><dd class="col-8">{{ $warehouse->slug ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Email</dt><dd class="col-8">{{ $warehouse->email ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Phone</dt><dd class="col-8">{{ $warehouse->phone ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Address</dt><dd class="col-8">{{ $warehouse->address ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Status</dt><dd class="col-8"><x-admin.status-badge :on="$warehouse->is_active" /></dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="row g-3">
                <div class="col-6 col-md-3"><x-admin.stat-card label="Bins"      :value="number_format($warehouse->bin_locations_count)" icon="bi-geo-alt" /></div>
                <div class="col-6 col-md-3"><x-admin.stat-card label="Parts"     :value="number_format($warehouse->parts_count)"        icon="bi-box-seam" /></div>
                <div class="col-6 col-md-3"><x-admin.stat-card label="Suppliers" :value="number_format($warehouse->suppliers_count)"    icon="bi-truck" /></div>
                <div class="col-6 col-md-3"><x-admin.stat-card label="Users"     :value="number_format($warehouse->users_count)"        icon="bi-people" /></div>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Bin locations ({{ $warehouse->binLocations->count() }})</h2>
                <div class="admin-table">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Zone</th>
                                <th>Aisle / Shelf</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($warehouse->binLocations as $bin)
                                <tr>
                                    <td>{{ $bin->code }}</td>
                                    <td>{{ $bin->zone ?? '—' }}</td>
                                    <td>{{ trim(($bin->aisle ?? '') . ' / ' . ($bin->shelf ?? ''), ' /') ?: '—' }}</td>
                                    <td><x-admin.status-badge :on="$bin->is_active" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-admin.empty-state icon="bi-geo-alt" title="No bin locations yet" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection