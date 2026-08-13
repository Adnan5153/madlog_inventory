@extends('layouts.admin', ['title' => 'Edit warehouse'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Warehouses', 'url' => route('admin.warehouses.index')],
        ['label' => $warehouse->name],
    ]" />

    <x-admin.page-header title="Edit warehouse" />

    <form method="POST" action="{{ route('admin.warehouses.update', $warehouse) }}">
        @csrf
        @method('PUT')
        @include('admin.warehouses._form', ['warehouse' => $warehouse])
    </form>
@endsection