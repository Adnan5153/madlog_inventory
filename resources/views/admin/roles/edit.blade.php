@extends('layouts.admin', ['title' => 'Edit role'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Roles', 'url' => route('admin.roles.index')],
        ['label' => $role->name],
    ]" />

    <x-admin.page-header title="Edit role" subtitle="Modify the role's metadata and the set of permissions it bundles." />

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @method('PUT')
        @include('admin.roles._form')
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning">
                <i class="bi bi-check-lg me-1"></i> Save changes
            </button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection