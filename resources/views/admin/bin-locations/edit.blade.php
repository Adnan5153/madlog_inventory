@extends('layouts.admin', ['title' => 'Edit bin location'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Bin locations', 'url' => route('admin.bin-locations.index')],
        ['label' => $bin->code],
    ]" />

    <x-admin.page-header title="Edit bin location" />

    <form method="POST" action="{{ route('admin.bin-locations.update', $bin) }}">
        @csrf
        @method('PUT')
        @include('admin.bin-locations._form', ['bin' => $bin, 'workshops' => $workshops])
    </form>
@endsection
