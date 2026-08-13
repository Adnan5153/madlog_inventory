@extends('layouts.admin', ['title' => 'Edit supplier'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Suppliers', 'url' => route('admin.suppliers.index')],
        ['label' => $supplier->name],
    ]" />

    <x-admin.page-header title="Edit supplier" subtitle="Update vendor details." />

    <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
        @csrf
        @method('PUT')
        @include('admin.suppliers._form', ['supplier' => $supplier])
    </form>
@endsection