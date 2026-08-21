@extends('layouts.admin', ['title' => 'Assign consumable'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment consumables', 'url' => route('admin.equipment-consumables.index')],
        ['label' => 'Assign'],
    ]" />

    <x-admin.page-header title="Assign consumable"
        subtitle="Register a Part, Battery or Lubricant against a piece of equipment." />

    <form method="POST" action="{{ route('admin.equipment-consumables.store') }}">
        @csrf
        @include('admin.equipment-consumables._form', [
            'consumable' => null,
            'equipment' => $equipment,
            'preselectedEquipment' => $preselectedEquipment,
            'preselectedEquipmentId' => $preselectedEquipment?->id ?? request('equipment_id'),
            'parts' => $parts,
            'batteries' => $batteries,
            'lubricants' => $lubricants,
            'units' => $units,
            'bins' => $bins,
            'allowedResources' => $allowedResources,
        ])
    </form>
@endsection