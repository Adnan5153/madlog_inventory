@extends('layouts.admin', ['title' => 'Stock movements'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Reports', 'url' => null], ['label' => 'Movement history']]" />

    <x-admin.page-header title="Stock movement history" subtitle="Most recent 200 movements in your workshop.">
        <x-slot:actions>
            <a href="{{ route('admin.reports.movements.export', request()->only('type')) }}" class="btn btn-outline-secondary">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </a>
            <form method="GET" action="{{ route('admin.reports.movements') }}" class="d-flex gap-2 align-items-center">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All types</option>
                    @foreach($types as $t)
                        <option value="{{ $t }}" @selected($type === $t)>{{ $t }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-table">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Type</th>
                    <th>Part</th>
                    <th>Bin</th>
                    <th class="text-end">Quantity</th>
                    <th>Reason</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movements as $m)
                    <tr>
                        <td class="small text-muted">{{ $m->occurred_at?->format('Y-m-d H:i') ?? $m->created_at->format('Y-m-d H:i') }}</td>
                        <td><x-admin.status-badge variant="info">{{ $m->type }}</x-admin.status-badge></td>
                        <td>{{ $m->part?->name ?? '—' }}</td>
                        <td>{{ $m->bin?->code ?? '—' }}</td>
                        <td class="text-end {{ (float) $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $m->quantity, 2) }}</td>
                        <td class="text-muted small">{{ $m->reason ?? '—' }}</td>
                        <td class="text-muted">{{ $m->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><x-admin.empty-state icon="bi-arrow-left-right" title="No movements yet" /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection