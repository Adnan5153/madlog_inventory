@extends('layouts.admin', ['title' => 'Suppliers'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Suppliers']]" />

    <x-admin.page-header title="Suppliers" subtitle="Vendor list. Workshop-scoped.">
        <x-slot:actions>
            <a href="{{ route('admin.supplier-categories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-tags me-1"></i> Categories
            </a>
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New supplier
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.suppliers.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.suppliers.search') }}">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name, contact, email..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.suppliers.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="supplier">
        {{ $suppliers->total() }} {{ \Illuminate\Support\Str::plural('supplier', $suppliers->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Email / Phone</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.suppliers._row-template', ['suppliers' => $suppliers])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $suppliers->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
