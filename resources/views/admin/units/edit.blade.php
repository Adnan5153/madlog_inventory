@extends('layouts.admin', ['title' => 'Edit unit'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Units', 'url' => route('admin.units.index')],
        ['label' => $unit->name],
    ]" />

    <x-admin.page-header title="Edit unit" :subtitle="'ID #' . $unit->id" />

    <form method="POST" action="{{ route('admin.units.update', $unit) }}">
        @csrf @method('PUT')
        @include('admin.units._form', ['unit' => $unit])

        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save changes</button>
            <a href="{{ route('admin.units.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection