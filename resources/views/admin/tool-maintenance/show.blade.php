@extends('layouts.admin', ['title' => 'Maintenance record · '.$tool->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => $tool->name, 'url' => route('admin.tools.show', $tool)],
        ['label' => 'Maintenance', 'url' => route('admin.tool-maintenance.index', $tool)],
        ['label' => $record->performed_at?->format('Y-m-d') ?? 'Record'],
    ]" />

    <x-admin.page-header title="Maintenance record" :subtitle="$tool->name">
        <x-slot:actions>
            <a href="{{ route('admin.tool-maintenance.index', $tool) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            @can('update', $record)
                <a href="{{ route('admin.tool-maintenance.edit', [$tool, $record]) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-card">
        <dl class="row mb-0">
            <dt class="col-4 text-muted">Type</dt>
            <dd class="col-8">{{ $record->type->label() }}</dd>
            <dt class="col-4 text-muted">Performed at</dt>
            <dd class="col-8">{{ $record->performed_at?->format('Y-m-d') ?? '—' }}</dd>
            <dt class="col-4 text-muted">Performed by</dt>
            <dd class="col-8">{{ $record->performedBy?->name ?? '—' }}</dd>
            <dt class="col-4 text-muted">Vendor</dt>
            <dd class="col-8">{{ $record->vendor ?? '—' }}</dd>
            <dt class="col-4 text-muted">Cost</dt>
            <dd class="col-8">{{ $record->cost !== null ? '$'.number_format((float) $record->cost, 2) : '—' }}</dd>
            <dt class="col-4 text-muted">Next due</dt>
            <dd class="col-8">
                @if($record->next_due_at)
                    <span class="{{ $record->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                        {{ $record->next_due_at->format('Y-m-d') }}
                        @if($record->isOverdue())<i class="bi bi-exclamation-triangle-fill ms-1"></i>@endif
                    </span>
                @else
                    —
                @endif
            </dd>
            <dt class="col-4 text-muted">Description</dt>
            <dd class="col-8">{{ $record->description }}</dd>
        </dl>
    </div>
@endsection
