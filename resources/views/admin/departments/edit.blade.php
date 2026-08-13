@extends('layouts.admin', ['title' => 'Edit department'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Departments', 'url' => route('admin.departments.index')],
        ['label' => $department->name],
    ]" />

    <x-admin.page-header title="Edit department" :subtitle="'ID #' . $department->id" />

    <form method="POST" action="{{ route('admin.departments.update', $department) }}">
        @csrf @method('PUT')
        @include('admin.departments._form', ['department' => $department, 'managers' => $managers])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning"><i class="bi bi-check-lg me-1"></i> Save changes</button>
            <a href="{{ route('admin.departments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection