@extends('layouts.admin', ['title' => 'New department'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Departments', 'url' => route('admin.departments.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New department" />

    <form method="POST" action="{{ route('admin.departments.store') }}">
        @csrf
        @include('admin.departments._form', ['department' => null, 'managers' => $managers])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning"><i class="bi bi-check-lg me-1"></i> Create department</button>
            <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection