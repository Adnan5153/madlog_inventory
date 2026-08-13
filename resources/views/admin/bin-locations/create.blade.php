@extends('layouts.admin', ['title' => 'New bin location'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Bin locations', 'url' => route('admin.bin-locations.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New bin location" />

    <form method="POST" action="{{ route('admin.bin-locations.store') }}">
        @csrf
        @include('admin.bin-locations._form', ['bin' => null])
    </form>
@endsection