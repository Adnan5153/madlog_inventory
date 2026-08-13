@extends('layouts.admin', ['title' => 'Departments'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Departments']]" />

    <x-admin.page-header title="Departments" subtitle="Operational consumers of inventory (Maintenance, Engineering, etc.).">
        <x-slot:actions>
            <a href="{{ route('admin.departments.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New department
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.departments.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-6 col-lg-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Department name...">
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
                    <th>Code</th>
                    <th>Manager</th>
                    <th class="text-end">Equipment</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($departments as $department)
                    <tr>
                        <td>
                            <a href="{{ route('admin.departments.edit', $department) }}" class="text-decoration-none">
                                {{ $department->name }}
                            </a>
                        </td>
                        <td><code>{{ $department->code }}</code></td>
                        <td>{{ $department->manager?->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format($department->equipment_count) }}</td>
                        <td>
                            <x-admin.status-badge :variant="$department->is_active ? 'success' : 'default'">
                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.status-badge>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="d-inline" data-confirm-form data-confirm="Delete this department? Equipment in it must be moved first.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">
                        <x-admin.empty-state icon="bi-diagram-3" title="No departments yet">
                            Create departments so equipment and inventory consumption can be attributed to an organizational unit.
                        </x-admin.empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $departments->links('vendor.pagination.bootstrap-5') }}</div>
@endsection