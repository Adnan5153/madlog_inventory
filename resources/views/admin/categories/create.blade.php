@extends('layouts.admin', ['title' => 'New category'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Categories', 'url' => route('admin.categories.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New category" subtitle="Group parts by an intuitive top-level category." />

    <form method="POST" action="{{ route('admin.categories.store') }}">
        @csrf
        @include('admin.categories._form', ['workshops' => $workshops])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Create category
            </button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection