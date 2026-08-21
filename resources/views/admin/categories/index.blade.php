@extends('layouts.admin', ['title' => 'Categories'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Categories']]" />

    <x-admin.page-header title="Categories" subtitle="Top-level grouping for parts.">
        <x-slot:actions>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New category
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.categories.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.categories.search') }}">
            <div class="col-12 col-md-6 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}"
                       class="form-control" placeholder="Category name…"
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <x-admin.clear-filters :route="route('admin.categories.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="category">
        {{ $categories->total() }} {{ \Illuminate\Support\Str::plural('category', $categories->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th class="text-end">Parts</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.categories._row-template', ['categories' => $categories])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $categories->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection