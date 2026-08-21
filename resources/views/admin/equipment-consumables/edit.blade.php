@extends('layouts.admin', ['title' => 'Edit consumable'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment consumables', 'url' => route('admin.equipment-consumables.index')],
        ['label' => $consumable->equipment?->name ?? 'Consumable', 'url' => route('admin.equipment-consumables.show', $consumable)],
        ['label' => 'Edit'],
    ]" />

    <x-admin.page-header title="Edit consumable"
        :subtitle="$consumable->equipment?->name . ' · ' . \App\Models\EquipmentConsumable::resourceLabel($consumable->resource_type)" />

    <form method="POST" action="{{ route('admin.equipment-consumables.update', $consumable) }}">
        @csrf
        @method('PUT')
        @include('admin.equipment-consumables._form', [
            'consumable' => $consumable,
            'equipment' => collect([$consumable->equipment])->filter(),
            'parts' => collect(),
            'batteries' => collect(),
            'lubricants' => collect(),
            'units' => collect(),
            'bins' => collect(),
            'allowedResources' => [],
        ])
    </form>
@endsection