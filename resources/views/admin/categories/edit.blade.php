@extends('layouts.admin', ['title' => 'Edit category'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Categories', 'url' => route('admin.categories.index')],
        ['label' => $category->name],
    ]" />

    <x-admin.page-header title="Edit category" :subtitle="'ID #' . $category->id" />

    <form method="POST" action="{{ route('admin.categories.update', $category) }}">
        @csrf
        @method('PUT')
        @include('admin.categories._form', ['category' => $category, 'workshops' => $workshops])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary">
                <i class="bi bi-check-lg me-1"></i> Save changes
            </button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection