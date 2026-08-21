@extends('layouts.admin', ['title' => 'Tool categories'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => 'Categories'],
    ]" />

    <x-admin.page-header title="Tool categories" subtitle="Group tools by family (hand tools, diagnostics, lifting, etc).">
        <x-slot:actions>
            <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to tools
            </a>
            <a href="{{ route('admin.tool-categories.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New category
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.tool-categories.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.tool-categories.search') }}">
            <x-admin.clear-filters :route="route('admin.tool-categories.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="toolCategory">
        {{ $categories->total() }} {{ \Illuminate\Support\Str::plural('toolCategory', $categories->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Slug</th>
                    <th class="text-end">Tools</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.tool-categories._row-template', ['categories' => $categories])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $categories->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection
