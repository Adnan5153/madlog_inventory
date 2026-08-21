@extends('layouts.admin', ['title' => 'Users'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Users']]" />

    <x-admin.page-header title="Users" subtitle="Manage authentication accounts, role assignments and per-workshop scoping.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> New user
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.users.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.users.search') }}">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name or email..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-select" data-live-search-control>
                    <option value="">All roles</option>
                    <option value="admin" @selected($role === 'admin')>Admin</option>
                    <option value="staff" @selected($role === 'staff')>Staff</option>
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.users.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="user">
        {{ $users->total() }} {{ \Illuminate\Support\Str::plural('user', $users->total()) }}
    </x-admin.live-search-status>

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
            <tbody data-live-search-target>
                @include('admin.users._row-template', ['users' => $users])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $users->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
