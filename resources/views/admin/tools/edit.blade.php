@extends('layouts.admin', ['title' => 'Edit tool'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => $tool->name, 'url' => route('admin.tools.show', $tool)],
        ['label' => 'Edit'],
    ]" />

    <x-admin.page-header title="Edit tool" :subtitle="$tool->tool_code" />

    <form method="POST" action="{{ route('admin.tools.update', $tool) }}">
        @csrf
        @method('PUT')
        @include('admin.tools._form', [
            'tool' => $tool,
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
