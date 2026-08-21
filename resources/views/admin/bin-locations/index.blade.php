@extends('layouts.admin', ['title' => 'Bin locations'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Bin locations']]" />

    <x-admin.page-header title="Bin locations" subtitle="Storage slots within a workshop. Bin codes are unique per workshop.">
        <x-slot:actions>
            <a href="{{ route('admin.bin-locations.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New bin
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.bin-locations.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.bin-locations.search') }}">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Code, zone, aisle..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3">
                <label for="zone" class="form-label">Zone</label>
                <select id="zone" name="zone" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($zones as $z)
                        <option value="{{ $z }}" @selected($zone === $z)>{{ $z }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.bin-locations.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="binLocation">
        {{ $binLocations->total() }} {{ \Illuminate\Support\Str::plural('binLocation', $binLocations->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Zone</th>
                    <th>Aisle</th>
                    <th>Shelf</th>
                    <th class="text-end">On hand</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.bin-locations._row-template', ['binLocations' => $binLocations])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $binLocations->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
