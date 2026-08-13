@extends('layouts.admin', ['title' => 'Goods receipts'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Goods receipts']]" />

    <x-admin.page-header title="Goods receipts" subtitle="GRNs recorded against purchase orders. Read-only — receipts are immutable." />

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.goods-receipts.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="GRN, supplier invoice...">
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
                    <th>GRN number</th>
                    <th>PO</th>
                    <th>Supplier</th>
                    <th>Received by</th>
                    <th>Status</th>
                    <th>Received at</th>
                    <th class="text-end">Lines</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $r)
                    <tr>
                        <td><a href="{{ route('admin.goods-receipts.show', $r) }}" class="text-decoration-none">{{ $r->grn_number }}</a></td>
                        <td>{{ $r->purchaseOrder?->po_number ?? '—' }}</td>
                        <td>{{ $r->purchaseOrder?->supplier?->name ?? '—' }}</td>
                        <td>{{ $r->receiver?->name ?? '—' }}</td>
                        <td><x-admin.status-badge :on="$r->status !== 'disputed'" :label="ucfirst($r->status)" /></td>
                        <td>{{ $r->received_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="text-end">{{ number_format($r->items_count) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty-state icon="bi-box-seam" title="No goods receipts yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $receipts->links('vendor.pagination.bootstrap-5') }}</div>
@endsection