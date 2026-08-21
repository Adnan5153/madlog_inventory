@extends('layouts.admin', ['title' => 'Edit maintenance record'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => $tool->name, 'url' => route('admin.tools.show', $tool)],
        ['label' => 'Maintenance', 'url' => route('admin.tool-maintenance.index', $tool)],
        ['label' => 'Edit'],
    ]" />

    <x-admin.page-header title="Edit maintenance record" :subtitle="$tool->name" />

    <form method="POST" action="{{ route('admin.tool-maintenance.update', [$tool, $record]) }}">
        @csrf
        @method('PUT')
        @include('admin.tool-maintenance._form', [
            'tool' => $tool,
            'record' => $record,
            'users' => $users,
        ])
    </form>
@endsection
