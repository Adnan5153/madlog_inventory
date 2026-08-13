@extends('layouts.admin', ['title' => 'Suppliers'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Suppliers']]" />

    <x-admin.page-header title="Suppliers" subtitle="Vendor list. Workshop-scoped.">
        <x-slot:actions>
            <a href="{{ route('admin.supplier-categories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-tags me-1"></i> Categories
            </a>
            <a href="{{ route('admin.suppliers.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New supplier
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.suppliers.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name, contact, email...">
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select">
                    <option value="">All</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select">
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
            </div>
            <div class="col-12 col-md-auto align-self-end">
                <button class="btn btn-outline-secondary">
                    <i class="bi bi-search"></i> Apply
                </button>
            </div>
        </form>
    </x-admin.filter-bar>

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
            <tbody>
                @forelse($suppliers as $s)
                    <tr>
                        <td>
                            <a href="{{ route('admin.suppliers.edit', $s) }}" class="text-decoration-none">{{ $s->name }}</a>
                            @if($s->tax_id)
                                <div class="small text-muted">Tax ID: {{ $s->tax_id }}</div>
                            @endif
                        </td>
                        <td>{{ $s->contact_name ?? '—' }}</td>
                        <td>
                            <div>{{ $s->email ?? '—' }}</div>
                            <div class="small text-muted">{{ $s->phone ?? '—' }}</div>
                        </td>
                        <td>{{ $s->category?->name ?? '—' }}</td>
                        <td><x-admin.status-badge :on="$s->is_active" /></td>
                        <td class="text-end">
                            <a href="{{ route('admin.suppliers.edit', $s) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.suppliers.destroy', $s) }}" class="d-inline" data-confirm-form data-confirm="Delete this supplier?">
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
                        <td colspan="6">
                            <x-admin.empty-state icon="bi-truck" title="No suppliers yet">
                                Add a vendor to start tracking purchase orders.
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $suppliers->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection