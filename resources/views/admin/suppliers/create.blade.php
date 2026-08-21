@extends('layouts.admin', ['title' => 'New supplier'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Suppliers', 'url' => route('admin.suppliers.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New supplier" subtitle="Add a vendor to the directory." />

    <form method="POST" action="{{ route('admin.suppliers.store') }}">
        @csrf
        @include('admin.suppliers._form', ['supplier' => null, 'workshops' => $workshops])
    </form>
@endsection
