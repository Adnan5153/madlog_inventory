@extends('layouts.admin', ['title' => 'Supplier categories'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Suppliers', 'url' => route('admin.suppliers.index')],
        ['label' => 'Categories'],
    ]" />

    <x-admin.page-header title="Supplier categories" subtitle="Group vendors by category (OEM, Aftermarket, etc).">
        <x-slot:actions>
            <a href="{{ route('admin.supplier-categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New category
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        {{-- Live-search wiring stub. The supplier-categories index has no
             filters today; this empty form exists so the live-search JS
             attaches cleanly and future filter inputs can be added here
             without any further plumbing. --}}
        <form method="GET" action="{{ route('admin.supplier-categories.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.supplier-categories.search') }}">
            <x-admin.clear-filters :route="route('admin.supplier-categories.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="supplierCategory">
        {{ $supplierCategories->total() }} {{ \Illuminate\Support\Str::plural('supplierCategory', $supplierCategories->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th class="text-end">Suppliers</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.supplier-categories._row-template', ['supplierCategories' => $supplierCategories])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $supplierCategories->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
