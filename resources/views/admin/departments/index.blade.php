@extends('layouts.admin', ['title' => 'Departments'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Departments']]" />

    <x-admin.page-header title="Departments" subtitle="Operational consumers of inventory (Maintenance, Engineering, etc.).">
        <x-slot:actions>
            <a href="{{ route('admin.departments.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New department
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.departments.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.departments.search') }}">
            <div class="col-12 col-md-6 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Department name..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <x-admin.clear-filters :route="route('admin.departments.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="department">
        {{ $departments->total() }} {{ \Illuminate\Support\Str::plural('department', $departments->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Manager</th>
                    <th class="text-end">Equipment</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.departments._row-template', ['departments' => $departments])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $departments->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
