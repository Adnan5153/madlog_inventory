@extends('layouts.admin', ['title' => 'New supplier category'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Suppliers', 'url' => route('admin.suppliers.index')],
        ['label' => 'Categories', 'url' => route('admin.supplier-categories.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New supplier category" />

    <form method="POST" action="{{ route('admin.supplier-categories.store') }}">
        @csrf
        @include('admin.supplier-categories._form', ['category' => null, 'workshops' => $workshops])
    </form>
@endsection
