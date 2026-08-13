@extends('layouts.admin', ['title' => 'Edit product'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Products', 'url' => route('admin.products.index')],
        ['label' => $product->name],
    ]" />

    <x-admin.page-header title="Edit product" subtitle="Update pricing, reorder policy, or classification." />

    <form method="POST" action="{{ route('admin.products.update', $product) }}">
        @csrf
        @method('PUT')
        @include('admin.products._form', ['product' => $product])
    </form>
@endsection