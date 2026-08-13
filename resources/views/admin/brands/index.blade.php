@extends('layouts.admin', ['title' => 'Brands'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Brands']]" />

    <x-admin.page-header title="Brands" subtitle="Manufacturers brands your workshop stocks.">
        <x-slot:actions>
            <a href="{{ route('admin.brands.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New brand
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.brands.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-6 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Brand name...">
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
                    <th>Slug</th>
                    <th class="text-end">Parts</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($brands as $brand)
                    <tr>
                        <td>
                            <a href="{{ route('admin.brands.edit', $brand) }}" class="text-decoration-none">
                                {{ $brand->name }}
                            </a>
                        </td>
                        <td class="text-muted">{{ $brand->slug }}</td>
                        <td class="text-end">{{ number_format($brand->parts_count) }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" class="d-inline" data-confirm-form data-confirm="Delete this brand? Parts in it must be moved first.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">
                        <x-admin.empty-state icon="bi-bookmark-star" title="No brands yet">
                            Add brands so parts can be grouped by manufacturer.
                        </x-admin.empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $brands->links('vendor.pagination.bootstrap-5') }}</div>
@endsection