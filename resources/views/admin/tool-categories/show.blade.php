@extends('layouts.admin', ['title' => $category->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => 'Categories', 'url' => route('admin.tool-categories.index')],
        ['label' => $category->name],
    ]" />

    <x-admin.page-header :title="$category->name" :subtitle="$category->slug">
        <x-slot:actions>
            <a href="{{ route('admin.tool-categories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            @can('update', $category)
                <a href="{{ route('admin.tool-categories.edit', $category) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-card">
        <dl class="row mb-0">
            <dt class="col-4 text-muted">Name</dt><dd class="col-8">{{ $category->name }}</dd>
            <dt class="col-4 text-muted">Slug</dt><dd class="col-8">{{ $category->slug ?? '—' }}</dd>
            <dt class="col-4 text-muted">Tools assigned</dt><dd class="col-8">{{ number_format($category->tools_count ?? 0) }}</dd>
            <dt class="col-4 text-muted">Status</dt><dd class="col-8"><x-admin.status-badge :on="$category->is_active" /></dd>
            <dt class="col-4 text-muted">Description</dt><dd class="col-8">{{ $category->description ?? '—' }}</dd>
        </dl>
    </div>
@endsection
