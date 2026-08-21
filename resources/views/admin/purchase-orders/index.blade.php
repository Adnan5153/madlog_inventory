@extends('layouts.admin', ['title' => 'Purchase orders'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Purchase orders']]" />

    <x-admin.page-header title="Purchase orders" subtitle="Outbound orders to suppliers. Lifecycle: draft → submitted → approved → received.">
        <x-slot:actions>
            <a href="{{ route('admin.purchase-orders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New purchase order
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.purchase-orders.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.purchase-orders.search') }}">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="PO number, notes..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select"
                        data-live-search-control>
                    <option value="">All statuses</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="supplier_id" class="form-label">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="form-select"
                        data-live-search-control>
                    <option value="">All suppliers</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected((string) $supplierId === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.purchase-orders.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="order">
        {{ $orders->total() }} {{ \Illuminate\Support\Str::plural('order', $orders->total()) }}
    </x-admin.live-search-status>

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
            <tbody data-live-search-target>
                @include('admin.purchase-orders._row-template', ['orders' => $orders])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $orders->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection