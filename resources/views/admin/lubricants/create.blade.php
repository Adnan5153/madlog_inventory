@extends('layouts.admin', ['title' => 'New lubricant'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Lubricants', 'url' => route('admin.lubricants.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New lubricant" subtitle="Add a lubricant SKU to the catalog." />

    <form method="POST" action="{{ route('admin.lubricants.store') }}">
        @csrf
        @include('admin.lubricants._form', [
            'lubricant' => null,
            'workshops' => $workshops,
            'pickedWorkshopId' => $pickedWorkshopId ?? null,
            'binLocations' => $binLocations,
            'suppliers' => $suppliers,
            'lubricantTypes' => $lubricantTypes,
            'viscosities' => $viscosities,
            'applications' => $applications,
            'packageTypes' => $packageTypes,
            'statuses' => $statuses,
        ])
    </form>
@endsection