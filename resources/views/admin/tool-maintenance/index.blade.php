@extends('layouts.admin', ['title' => 'Maintenance · '.$tool->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => $tool->name, 'url' => route('admin.tools.show', $tool)],
        ['label' => 'Maintenance'],
    ]" />

    <x-admin.page-header
        :title="'Maintenance · '.$tool->name"
        :subtitle="$tool->tool_code">
        <x-slot:actions>
            <a href="{{ route('admin.tools.show', $tool) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to tool
            </a>
            @can('recordMaintenance', $tool)
                <a href="{{ route('admin.tool-maintenance.create', $tool) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Record maintenance
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.tool-maintenance.index', $tool) }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.tool-maintenance.search', $tool) }}">
            <x-admin.clear-filters :route="route('admin.tool-maintenance.index', $tool)" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="maintenance record">
        {{ $records->total() }} {{ \Illuminate\Support\Str::plural('record', $records->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Performed</th>
                    <th>By</th>
                    <th>Vendor</th>
                    <th class="text-end">Cost</th>
                    <th>Next due</th>
                    <th class="text-end" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.tool-maintenance._row-template', ['records' => $records])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $records->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
