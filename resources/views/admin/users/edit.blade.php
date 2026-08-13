@extends('layouts.admin', ['title' => 'Edit user'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Users', 'url' => route('admin.users.index')],
        ['label' => $user->name],
    ]" />

    <x-admin.page-header title="Edit user" subtitle="Update profile, password, workshop scope, and RBAC role membership." />

    <form method="POST" action="{{ route('admin.users.update', $user) }}">
        @method('PUT')
        @include('admin.users._form')
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning">
                <i class="bi bi-check-lg me-1"></i> Save changes
            </button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection