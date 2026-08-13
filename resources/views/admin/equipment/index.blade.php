@extends('layouts.admin', ['title' => 'Equipment'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Equipment']]" />

    <x-admin.page-header title="Equipment" subtitle="Asset register for tools, lifts, scanners, etc.">
        <x-slot:actions>
            <a href="{{ route('admin.equipment.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New equipment
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.equipment.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name, asset #, or serial...">
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach(['active','maintenance','retired','disposed'] as $opt)
                        <option value="{{ $opt }}" @selected($status === $opt)>{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto align-self-end">
                <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Apply</button>
            </div>
        </form>
    </x-admin.filter-bar>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Asset #</th>
                    <th>Department</th>
                    <th>Manufacturer</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($equipment as $item)
                    <tr>
                        <td>
                            <a href="{{ route('admin.equipment.show', $item) }}" class="text-decoration-none">
                                {{ $item->name }}
                            </a>
                            <div class="text-muted small">{{ $item->model }}</div>
                        </td>
                        <td><code>{{ $item->asset_number ?? '—' }}</code></td>
                        <td>{{ $item->department?->name ?? '—' }}</td>
                        <td class="text-muted">{{ $item->manufacturer ?? '—' }}</td>
                        <td>
                            <x-admin.status-badge :variant="match($item->status) {
                                'active' => 'success',
                                'maintenance' => 'warning',
                                'retired', 'disposed' => 'danger',
                                default => 'default',
                            }">
                                {{ ucfirst($item->status) }}
                            </x-admin.status-badge>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.equipment.edit', $item) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.equipment.destroy', $item) }}" class="d-inline" data-confirm-form data-confirm="Delete this equipment? This action cannot be undone.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <x-admin.empty-state icon="bi-tools" title="No equipment yet">
                            Add the asset register so equipment can be linked to inventory consumption.
                        </x-admin.empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $equipment->links('vendor.pagination.bootstrap-5') }}</div>
@endsection