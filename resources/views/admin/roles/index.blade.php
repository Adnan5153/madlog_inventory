@extends('layouts.admin', ['title' => 'Roles'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Roles']]" />

    <x-admin.page-header title="Roles" subtitle="Bundles of permissions. Roles are the primary way to grant abilities to users; permissions can also be granted directly.">
        <x-slot:actions>
            <a href="{{ route('admin.permissions.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-key me-1"></i> Permission catalogue
            </a>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New role
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Type</th>
                    <th class="text-end">Users</th>
                    <th class="text-end">Permissions</th>
                    <th class="text-end" style="width: 200px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td>
                            <a href="{{ route('admin.roles.show', $role) }}" class="text-decoration-none">{{ $role->name }}</a>
                            @if($role->is_system)
                                <span class="badge text-bg-secondary ms-1">system</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $role->slug }}</td>
                        <td class="text-muted small">{{ $role->is_system ? 'Built-in' : 'Custom' }}</td>
                        <td class="text-end">{{ number_format($role->users_count) }}</td>
                        <td class="text-end">{{ number_format($role->permissions_count) }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            @if(! $role->is_system)
                                <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline" data-confirm-form data-confirm="Delete this role? Users with only this role lose its permissions on next request.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state icon="bi-shield-lock" title="No roles yet" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection