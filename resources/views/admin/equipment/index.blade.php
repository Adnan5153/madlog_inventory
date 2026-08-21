@extends('layouts.admin', ['title' => 'Equipment'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Equipment']]" />

    <x-admin.page-header title="Equipment" subtitle="Asset register for tools, lifts, scanners, etc.">
        <x-slot:actions>
            <a href="{{ route('admin.equipment.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New equipment
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.equipment.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.equipment.search') }}">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name, asset #, or serial..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach(['active','maintenance','retired','disposed'] as $opt)
                        <option value="{{ $opt }}" @selected($status === $opt)>{{ ucfirst($opt) }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.equipment.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="equipment">
        {{ $equipment->total() }} {{ \Illuminate\Support\Str::plural('equipment', $equipment->total()) }}
    </x-admin.live-search-status>

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
            <tbody data-live-search-target>
                @include('admin.equipment._row-template', ['equipment' => $equipment])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $equipment->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
