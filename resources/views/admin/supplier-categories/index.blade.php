@extends('layouts.admin', ['title' => 'Supplier categories'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Suppliers', 'url' => route('admin.suppliers.index')],
        ['label' => 'Categories'],
    ]" />

    <x-admin.page-header title="Supplier categories" subtitle="Group vendors by category (OEM, Aftermarket, etc).">
        <x-slot:actions>
            <a href="{{ route('admin.supplier-categories.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New category
            </a>
        </x-slot:actions>
    </x-admin.page-header>

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
            <tbody>
                @forelse($categories as $cat)
                    <tr>
                        <td>{{ $cat->name }}</td>
                        <td class="text-muted">{{ $cat->code }}</td>
                        <td class="text-end">{{ number_format($cat->suppliers_count) }}</td>
                        <td><x-admin.status-badge :on="$cat->is_active" /></td>
                        <td class="text-end">
                            <a href="{{ route('admin.supplier-categories.edit', $cat) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.supplier-categories.destroy', $cat) }}" class="d-inline" data-confirm-form data-confirm="Delete this category?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5"><x-admin.empty-state icon="bi-tags" title="No supplier categories yet" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $categories->links('vendor.pagination.bootstrap-5') }}</div>
@endsection