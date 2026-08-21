@extends('layouts.admin', ['title' => 'Edit battery'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Batteries', 'url' => route('admin.batteries.index')],
        ['label' => $battery->name],
    ]" />

    <x-admin.page-header title="Edit battery" subtitle="Update specs, classification, pricing or reorder policy." />

    <form method="POST" action="{{ route('admin.batteries.update', $battery) }}">
        @csrf
        @method('PUT')
        @include('admin.batteries._form', [
            'battery' => $battery,
            'workshops' => $workshops,
            'pickedWorkshopId' => $pickedWorkshopId ?? null,
            'binLocations' => $binLocations,
            'suppliers' => $suppliers,
            'chemistries' => $chemistries,
            'statuses' => $statuses,
        ])
    </form>
@endsection
