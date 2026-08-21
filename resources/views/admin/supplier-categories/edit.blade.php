@extends('layouts.admin', ['title' => 'Edit supplier category'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Suppliers', 'url' => route('admin.suppliers.index')],
        ['label' => 'Categories', 'url' => route('admin.supplier-categories.index')],
        ['label' => $category->name],
    ]" />

    <x-admin.page-header title="Edit supplier category" />

    <form method="POST" action="{{ route('admin.supplier-categories.update', $category) }}">
        @csrf
        @method('PUT')
        @include('admin.supplier-categories._form', ['category' => $category, 'workshops' => $workshops])
    </form>
@endsection
