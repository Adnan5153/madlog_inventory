@extends('layouts.admin', ['title' => 'Purchase orders'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Purchase orders']]" />

    <x-admin.page-header title="Purchase orders" subtitle="Outbound orders to suppliers. Lifecycle: draft → submitted → approved → received.">
        <x-slot:actions>
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-warning">
                <i class="bi bi-plus-lg me-1"></i> New purchase order
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.purchase-orders.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="PO number, notes...">
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="supplier_id" class="form-label">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="form-select">
                    <option value="">All suppliers</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected((string) $supplierId === (string) $s->id)>{{ $s->name }}</option>
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
                    <th>PO number</th>
                    <th>Supplier</th>
                    <th>Created by</th>
                    <th>Status</th>
                    <th>Order date</th>
                    <th class="text-end">Items</th>
                    <th class="text-end">Total</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                    <tr>
                        <td><a href="{{ route('admin.purchase-orders.show', $o) }}" class="text-decoration-none">{{ $o->po_number }}</a></td>
                        <td>{{ $o->supplier?->name ?? '—' }}</td>
                        <td>{{ $o->creator?->name ?? '—' }}</td>
                        <td><x-admin.status-badge :on="!in_array($o->status, ['cancelled','draft'])" :label="ucfirst(str_replace('_', ' ', $o->status))" /></td>
                        <td>{{ $o->order_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-end">{{ number_format($o->items_count) }}</td>
                        <td class="text-end">${{ number_format($o->total, 2) }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.purchase-orders.show', $o) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty-state icon="bi-bag" title="No purchase orders yet" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $orders->links('vendor.pagination.bootstrap-5') }}</div>
@endsection
