@extends('layouts.admin', ['title' => 'New unit'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Units', 'url' => route('admin.units.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New unit" />

    <form method="POST" action="{{ route('admin.units.store') }}">
        @csrf
        @include('admin.units._form')

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Create unit</button>
            <a href="{{ route('admin.units.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection