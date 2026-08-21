@extends('layouts.admin', ['title' => 'New tool'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New tool" subtitle="Register a single physical asset in the workshop catalog." />

    <form method="POST" action="{{ route('admin.tools.store') }}">
        @csrf
        @include('admin.tools._form', [
            'tool' => null,
            'workshops' => $workshops,
            'pickedWorkshopId' => $pickedWorkshopId ?? null,
            'binLocations' => $binLocations,
            'suppliers' => $suppliers,
            'categories' => $categories,
            'users' => $users,
            'statuses' => $statuses,
            'conditions' => $conditions,
        ])
    </form>
@endsection
