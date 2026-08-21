@extends('layouts.admin', ['title' => 'Edit lubricant'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Lubricants', 'url' => route('admin.lubricants.index')],
        ['label' => $lubricant->name],
    ]" />

    <x-admin.page-header title="Edit lubricant" subtitle="Update specs, classification, packaging, pricing or reorder policy." />

    <form method="POST" action="{{ route('admin.lubricants.update', $lubricant) }}">
        @csrf
        @method('PUT')
        @include('admin.lubricants._form', [
            'lubricant' => $lubricant,
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