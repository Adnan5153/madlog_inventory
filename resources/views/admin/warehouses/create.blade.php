@extends('layouts.admin', ['title' => 'New warehouse'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Warehouses', 'url' => route('admin.warehouses.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New warehouse" subtitle="Set up a new tenant." />

    <form method="POST" action="{{ route('admin.warehouses.store') }}">
        @csrf
        @include('admin.warehouses._form', ['warehouse' => null])
    </form>
@endsection