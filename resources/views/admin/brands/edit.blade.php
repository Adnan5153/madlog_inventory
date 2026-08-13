@extends('layouts.admin', ['title' => 'Edit brand'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Brands', 'url' => route('admin.brands.index')],
        ['label' => $brand->name],
    ]" />

    <x-admin.page-header title="Edit brand" :subtitle="'ID #' . $brand->id" />

    <form method="POST" action="{{ route('admin.brands.update', $brand) }}">
        @csrf @method('PUT')
        @include('admin.brands._form', ['brand' => $brand])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning"><i class="bi bi-check-lg me-1"></i> Save changes</button>
            <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection