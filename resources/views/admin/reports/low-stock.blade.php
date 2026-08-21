@extends('layouts.admin', ['title' => 'Low stock'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Reports', 'url' => null], ['label' => 'Low stock']]" />

    <x-admin.page-header title="Low-stock report" subtitle="Parts whose aggregated on-hand quantity has reached or fallen below the reorder threshold.">
        <x-slot:actions>
            <a href="{{ route('admin.reports.low-stock.export') }}" class="btn btn-outline-secondary">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Part</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th class="text-end">On hand</th>
                    <th class="text-end">Reorder ≤</th>
                    <th class="text-end">Reorder qty</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($parts as $p)
                    <tr>
                        <td>
                            <a href="{{ route('admin.products.show', $p) }}" class="text-decoration-none">{{ $p->name }}</a>
                            <div class="small text-muted">{{ $p->sku ?? '—' }}</div>
                        </td>
                        <td>{{ $p->category?->name ?? '—' }}</td>
                        <td>{{ $p->brand ?? '—' }}</td>
                        <td class="text-end fw-semibold text-danger">{{ number_format((float) ($p->on_hand ?? 0), 2) }}</td>
                        <td class="text-end">{{ number_format($p->reorder_threshold) }}</td>
                        <td class="text-end">{{ number_format($p->reorder_quantity) }}</td>
                        <td>
                            @if((float) ($p->on_hand ?? 0) <= 0)
                                <x-admin.status-badge variant="danger">Out</x-admin.status-badge>
                            @else
                                <x-admin.status-badge variant="warning">Critical</x-admin.status-badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7"><x-admin.empty-state icon="bi-check2-circle" title="Stock is healthy">No parts have crossed their reorder threshold.</x-admin.empty-state></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection