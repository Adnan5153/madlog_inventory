@extends('layouts.admin', ['title' => 'New role'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Roles', 'url' => route('admin.roles.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New role" subtitle="Define a name, slug, description and the set of permissions this role bundles." />

    <form method="POST" action="{{ route('admin.roles.store') }}">
        @include('admin.roles._form')
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning">
                <i class="bi bi-check-lg me-1"></i> Create role
            </button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection