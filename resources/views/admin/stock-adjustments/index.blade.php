@extends('layouts.admin', ['title' => 'Stock adjustments'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Stock adjustments']]" />

    <x-admin.page-header title="Stock adjustments" subtitle="Cycle counts, shrinkage, damage — anything that moves on-hand without a PO or transfer.">
        <x-slot:actions>
            <a href="{{ route('admin.stock-adjustments.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New adjustment
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.stock-adjustments.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Number, reason...">
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-auto align-self-end">
                <button class="btn btn-outline-secondary">
                    <i class="bi bi-search"></i> Apply
                </button>
            </div>
        </form>
    </x-admin.filter-bar>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Requested by</th>
                    <th>Approved by</th>
                    <th class="text-end">Lines</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $a)
                    <tr>
                        <td><a href="{{ route('admin.stock-adjustments.show', $a) }}" class="text-decoration-none">{{ $a->adjustment_number }}</a></td>
                        <td>{{ ucfirst(str_replace('_', ' ', $a->reason)) }}</td>
                        <td><x-admin.status-badge :on="$a->status === 'applied'" :label="ucfirst($a->status)" /></td>
                        <td>{{ $a->requester?->name ?? '—' }}</td>
                        <td>{{ $a->approver?->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format($a->items_count) }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.stock-adjustments.show', $a) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty-state icon="bi-sliders" title="No stock adjustments yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $adjustments->links('vendor.pagination.bootstrap-5') }}</div>
@endsection
