@extends('layouts.admin', ['title' => $permission->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Permissions', 'url' => route('admin.permissions.index')],
        ['label' => $permission->name],
    ]" />

    <x-admin.page-header :title="$permission->name" :subtitle="$permission->description ?? 'No description.'" />

    <div class="admin-card">
        <h2 class="h6 mb-3">Roles that grant this permission</h2>
        <div class="row g-2">
            @forelse($roles as $role)
                <div class="col-md-4">
                    <a href="{{ route('admin.roles.show', $role) }}" class="text-decoration-none">
                        <div class="border rounded p-2">
                            <strong>{{ $role->name }}</strong>
                            <div class="text-muted small"><code>{{ $role->slug }}</code></div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12 text-muted small">No roles currently grant this permission.</div>
            @endforelse
        </div>
    </div>
@endsection