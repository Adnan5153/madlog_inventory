@extends('layouts.admin', ['title' => 'Units of measure'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Units of measure']]" />

    <x-admin.page-header title="Units of measure" subtitle="How quantities are measured. Global across all workshops.">
        <x-slot:actions>
            <a href="{{ route('admin.units.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New unit
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.units.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name or short code...">
            </div>
            <div class="col-12 col-md-4 col-lg-3">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select">
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
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
                    <th>Short code</th>
                    <th class="text-end">Decimals</th>
                    <th class="text-end">Parts</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($units as $unit)
                    <tr>
                        <td>
                            <a href="{{ route('admin.units.edit', $unit) }}" class="text-decoration-none">
                                {{ $unit->name }}
                            </a>
                            @if($unit->description)
                                <div class="text-muted small">{{ $unit->description }}</div>
                            @endif
                        </td>
                        <td><code>{{ $unit->short_code }}</code></td>
                        <td class="text-end">{{ $unit->decimal_precision }}</td>
                        <td class="text-end">{{ number_format($unit->parts()->count()) }}</td>
                        <td>
                            <x-admin.status-badge :variant="$unit->is_active ? 'success' : 'default'">
                                {{ $unit->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.status-badge>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.units.edit', $unit) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.units.destroy', $unit) }}" class="d-inline" data-confirm-form data-confirm="Delete this unit? Parts and bins using it must be reassigned first.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <x-admin.empty-state icon="bi-rulers" title="No units yet">
                            Add units so parts can be measured in kg, L, pieces, etc.
                        </x-admin.empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $units->links('vendor.pagination.bootstrap-5') }}</div>
@endsection