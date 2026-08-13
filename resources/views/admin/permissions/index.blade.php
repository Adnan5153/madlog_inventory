@extends('layouts.admin', ['title' => 'Permissions'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Roles', 'url' => route('admin.roles.index')],
        ['label' => 'Permissions'],
    ]" />

    <x-admin.page-header title="Permission catalogue" subtitle="Every ability registered with Laravel's Gate facade. Read-only; permissions are managed via roles." />

    <div class="row g-3">
        @foreach($grouped as $group => $perms)
            <div class="col-12 col-md-6 col-xl-4">
                <div class="admin-card h-100">
                    <h2 class="h6 mb-2">{{ ucfirst($group) }}</h2>
                    <p class="text-muted small mb-3">{{ $perms->count() }} permission(s)</p>
                    <ul class="list-unstyled mb-0">
                        @foreach($perms as $perm)
                            <li class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                <a href="{{ route('admin.permissions.show', $perm) }}" class="text-decoration-none"><code>{{ $perm->name }}</code></a>
                                <span class="text-muted small">{{ $perm->roles_count ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
@endsection