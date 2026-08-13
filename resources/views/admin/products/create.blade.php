@extends('layouts.admin', ['title' => 'New product'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Products', 'url' => route('admin.products.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New product" subtitle="Add a part to the catalog." />

    <form method="POST" action="{{ route('admin.products.store') }}">
        @csrf
        @include('admin.products._form', ['product' => null])
    </form>
@endsection