@extends('layouts.admin', ['title' => 'Units of measure'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Units of measure']]" />

    <x-admin.page-header title="Units of measure" subtitle="How quantities are measured. Global across all workshops.">
        <x-slot:actions>
            <a href="{{ route('admin.units.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New unit
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.units.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.units.search') }}">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name or short code..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.units.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="unit">
        {{ $units->total() }} {{ \Illuminate\Support\Str::plural('unit', $units->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Short code</th>
                    <th class="text-end">Decimals</th>
                    <th class="text-end">Parts</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.units._row-template', ['units' => $units])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $units->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
