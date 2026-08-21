@extends('layouts.admin', ['title' => 'Edit equipment'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment', 'url' => route('admin.equipment.index')],
        ['label' => $equipment->name],
    ]" />

    <x-admin.page-header title="Edit equipment" :subtitle="'ID #' . $equipment->id" />

    <form method="POST" action="{{ route('admin.equipment.update', $equipment) }}">
        @csrf @method('PUT')
        @include('admin.equipment._form', [
            'equipment' => $equipment,
            'departments' => $departments,
            'bins' => $bins,
            'workshops' => $workshops,
        ])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save changes</button>
            <a href="{{ route('admin.equipment.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
