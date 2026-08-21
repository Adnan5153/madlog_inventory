@extends('layouts.admin', ['title' => $role->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Roles', 'url' => route('admin.roles.index')],
        ['label' => $role->name],
    ]" />

    <x-admin.page-header :title="$role->name" :subtitle="$role->description ?? 'No description.'">
        <x-slot:actions>
            <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Name</dt><dd class="col-8">{{ $role->name }}</dd>
                    <dt class="col-4 text-muted">Slug</dt><dd class="col-8"><code>{{ $role->slug }}</code></dd>
                    <dt class="col-4 text-muted">Type</dt><dd class="col-8">{{ $role->is_system ? 'Built-in' : 'Custom' }}</dd>
                    <dt class="col-4 text-muted">Created</dt><dd class="col-8">{{ $role->created_at->format('Y-m-d') }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="admin-card">
                <h2 class="h6 mb-3">Permissions granted ({{ $role->permissions->count() }})</h2>
                @php
                    $byGroup = $role->permissions->groupBy('group');
                @endphp
                @foreach($grouped as $group => $perms)
                    @php $granted = $byGroup->get($group, collect()); @endphp
                    <div class="mb-3">
                        <h3 class="h6 mb-2">{{ ucfirst($group) }} <span class="text-muted small">({{ $granted->count() }}/{{ $perms->count() }})</span></h3>
                        <div class="row g-1">
                            @foreach($perms as $perm)
                                <div class="col-md-6 col-lg-4">
                                    <span class="badge {{ $granted->contains('id', $perm->id) ? 'text-bg-success' : 'text-bg-light' }} me-1 mb-1">
                                        {{ $perm->name }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection