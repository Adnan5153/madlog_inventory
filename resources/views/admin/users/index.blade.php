@extends('layouts.admin', ['title' => 'Users'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Users']]" />

    <x-admin.page-header title="Users" subtitle="Manage authentication accounts, role assignments and per-workshop scoping.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-warning">
                <i class="bi bi-person-plus me-1"></i> New user
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name or email...">
            </div>
            <div class="col-6 col-md-3">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-select">
                    <option value="">All roles</option>
                    <option value="admin" @selected($role === 'admin')>Admin</option>
                    <option value="staff" @selected($role === 'staff')>Staff</option>
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
                    <th>Email</th>
                    <th>Workshop</th>
                    <th>Role</th>
                    <th>RBAC roles</th>
                    <th class="text-end" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td class="text-muted">{{ $user->email }}</td>
                        <td>{{ $user->workshop?->name ?? '—' }}</td>
                        <td>
                            <x-admin.status-badge :on="$user->isAdmin()" :label="ucfirst($user->role ?? 'none')" />
                        </td>
                        <td>
                            @forelse($user->rbacRoles as $r)
                                <span class="badge text-bg-secondary me-1">{{ $r->name }}</span>
                            @empty
                                <span class="text-muted small">—</span>
                            @endforelse
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline" data-confirm-form data-confirm="Delete this user?">
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
                            <x-admin.empty-state icon="bi-people" title="No users yet" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $users->links('vendor.pagination.bootstrap-5') }}</div>
@endsection