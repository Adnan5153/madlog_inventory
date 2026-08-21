@extends('layouts.admin', ['title' => 'Warehouses'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Warehouses']]" />

    <x-admin.page-header title="Warehouses" subtitle="Tenant root: every workshop-scoped record hangs off a warehouse.">
        <x-slot:actions>
            @if($user?->isAdmin() && $user?->workshop_id === null)
                <a href="{{ route('admin.warehouses.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> New warehouse
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.warehouses.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.warehouses.search') }}">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name, slug..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.warehouses.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="warehouse">
        {{ $warehouses->total() }} {{ \Illuminate\Support\Str::plural('warehouse', $warehouses->total()) }}
    </x-admin.live-search-status>

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
            <tbody data-live-search-target>
                @include('admin.warehouses._row-template', ['warehouses' => $warehouses])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $warehouses->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
