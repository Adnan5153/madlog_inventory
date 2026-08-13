@extends('layouts.admin', ['title' => 'Warehouses'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Warehouses']]" />

    <x-admin.page-header title="Warehouses" subtitle="Tenant root: every workshop-scoped record hangs off a warehouse.">
        <x-slot:actions>
            @if($user?->isAdmin() && $user?->workshop_id === null)
                <a href="{{ route('admin.warehouses.create') }}" class="btn btn-warning">
                    <i class="bi bi-plus-lg me-1"></i> New warehouse
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.warehouses.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name, slug...">
            </div>
            <div class="col-6 col-md-3">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select">
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
            </div>
            <div class="col-12 col-md-auto align-self-end">
                <button class="btn btn-outline-secondary">
                    <i class="bi bi-search"></i> Apply
                </button>
            </div>
        </form>
    </x-admin.filter-bar>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th class="text-end">Bins</th>
                    <th class="text-end">Parts</th>
                    <th class="text-end">Suppliers</th>
                    <th class="text-end">Users</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($warehouses as $w)
                    <tr>
                        <td><a href="{{ route('admin.warehouses.show', $w) }}" class="text-decoration-none">{{ $w->name }}</a></td>
                        <td class="text-muted">{{ $w->slug ?? '—' }}</td>
                        <td class="text-end">{{ number_format($w->bin_locations_count) }}</td>
                        <td class="text-end">{{ number_format($w->parts_count) }}</td>
                        <td class="text-end">{{ number_format($w->suppliers_count) }}</td>
                        <td class="text-end">{{ number_format($w->users_count) }}</td>
                        <td><x-admin.status-badge :on="$w->is_active" /></td>
                        <td class="text-end">
                            <a href="{{ route('admin.warehouses.show', $w) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if($user?->isAdmin() && $user?->workshop_id === null)
                                <a href="{{ route('admin.warehouses.edit', $w) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.warehouses.destroy', $w) }}" class="d-inline" data-confirm-form data-confirm="Archive this warehouse? Records stay in the database.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-archive"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty-state icon="bi-building" title="No warehouses yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $warehouses->links('vendor.pagination.bootstrap-5') }}</div>
@endsection