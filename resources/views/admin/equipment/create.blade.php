@extends('layouts.admin', ['title' => 'New equipment'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment', 'url' => route('admin.equipment.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New equipment" />

    <form method="POST" action="{{ route('admin.equipment.store') }}">
        @csrf
        @include('admin.equipment._form', [
            'equipment' => null,
            'departments' => $departments,
            'bins' => $bins,
        ])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-warning"><i class="bi bi-check-lg me-1"></i> Create equipment</button>
            <a href="{{ route('admin.equipment.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection