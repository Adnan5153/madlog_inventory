@extends('layouts.admin', ['title' => 'New brand'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Brands', 'url' => route('admin.brands.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New brand" />

    <form method="POST" action="{{ route('admin.brands.store') }}">
        @csrf
        @include('admin.brands._form')

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning"><i class="bi bi-check-lg me-1"></i> Create brand</button>
            <a href="{{ route('admin.brands.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection