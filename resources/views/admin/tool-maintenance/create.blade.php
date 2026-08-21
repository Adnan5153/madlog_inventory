@extends('layouts.admin', ['title' => 'Record maintenance · '.$tool->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => $tool->name, 'url' => route('admin.tools.show', $tool)],
        ['label' => 'Maintenance', 'url' => route('admin.tool-maintenance.index', $tool)],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="Record maintenance" :subtitle="$tool->name" />

    <form method="POST" action="{{ route('admin.tool-maintenance.store', $tool) }}">
        @csrf
        @include('admin.tool-maintenance._form', [
            'tool' => $tool,
            'record' => null,
            'users' => $users,
        ])
    </form>
@endsection
