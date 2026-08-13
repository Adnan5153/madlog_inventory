@extends('layouts.admin', ['title' => 'Stock transfers'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Stock transfers']]" />

    <x-admin.page-header title="Stock transfers" subtitle="Inter-bin moves. Atomic decrement on the source, increment on the destination.">
        <x-slot:actions>
            <a href="{{ route('admin.stock-transfers.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New transfer
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.stock-transfers.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Transfer number...">
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
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
                    <th>Source</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Transferred by</th>
                    <th>Received by</th>
                    <th class="text-end">Lines</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                    <tr>
                        <td><a href="{{ route('admin.stock-transfers.show', $t) }}" class="text-decoration-none">{{ $t->transfer_number }}</a></td>
                        <td>{{ $t->sourceBin?->code ?? '—' }}</td>
                        <td>{{ $t->destinationBin?->code ?? '—' }}</td>
                        <td><x-admin.status-badge :on="$t->status === 'received'" :label="ucfirst(str_replace('_', ' ', $t->status))" /></td>
                        <td>{{ $t->transferer?->name ?? '—' }}</td>
                        <td>{{ $t->receiver?->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format($t->items_count) }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.stock-transfers.show', $t) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty-state icon="bi-arrow-left-right" title="No stock transfers yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $transfers->links('vendor.pagination.bootstrap-5') }}</div>
@endsection
