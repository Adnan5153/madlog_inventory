@extends('layouts.admin', ['title' => 'New battery'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Batteries', 'url' => route('admin.batteries.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New battery" subtitle="Add a battery SKU to the catalog." />

    <form method="POST" action="{{ route('admin.batteries.store') }}">
        @csrf
        @include('admin.batteries._form', [
            'battery' => null,
            'workshops' => $workshops,
            'pickedWorkshopId' => $pickedWorkshopId ?? null,
            'binLocations' => $binLocations,
            'suppliers' => $suppliers,
            'chemistries' => $chemistries,
            'statuses' => $statuses,
        ])
    </form>
@endsection
