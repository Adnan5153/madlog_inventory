@extends('layouts.admin', ['title' => 'New user'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Users', 'url' => route('admin.users.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New user" subtitle="Create a login, pick a workshop scope and assign one or more RBAC roles." />

    <form method="POST" action="{{ route('admin.users.store') }}">
        @include('admin.users._form')
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Create user
            </button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection