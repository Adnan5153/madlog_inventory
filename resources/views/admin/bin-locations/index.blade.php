@extends('layouts.admin', ['title' => 'Bin locations'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Bin locations']]" />

    <x-admin.page-header title="Bin locations" subtitle="Storage slots within a workshop. Bin codes are unique per workshop.">
        <x-slot:actions>
            <a href="{{ route('admin.bin-locations.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New bin
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.bin-locations.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Code, zone, aisle...">
            </div>
            <div class="col-6 col-md-3">
                <label for="zone" class="form-label">Zone</label>
                <select id="zone" name="zone" class="form-select">
                    <option value="">All</option>
                    @foreach($zones as $z)
                        <option value="{{ $z }}" @selected($zone === $z)>{{ $z }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
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
                    <th>Code</th>
                    <th>Zone</th>
                    <th>Aisle</th>
                    <th>Shelf</th>
                    <th class="text-end">On hand</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bins as $b)
                    <tr>
                        <td><strong>{{ $b->code }}</strong></td>
                        <td>{{ $b->zone ?? '—' }}</td>
                        <td>{{ $b->aisle ?? '—' }}</td>
                        <td>{{ $b->shelf ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float) ($b->on_hand ?? 0), 2) }}</td>
                        <td><x-admin.status-badge :on="$b->is_active" /></td>
                        <td class="text-end">
                            <a href="{{ route('admin.bin-locations.edit', $b) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.bin-locations.destroy', $b) }}" class="d-inline" data-confirm-form data-confirm="Delete this bin location?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty-state icon="bi-geo-alt" title="No bin locations yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $bins->links('vendor.pagination.bootstrap-5') }}</div>
@endsection