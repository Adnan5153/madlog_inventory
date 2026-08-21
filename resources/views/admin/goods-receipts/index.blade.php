@extends('layouts.admin', ['title' => 'Goods receipts'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Goods receipts']]" />

    <x-admin.page-header title="Goods receipts" subtitle="GRNs recorded against purchase orders. Read-only — receipts are immutable." />

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.goods-receipts.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.goods-receipts.search') }}">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="GRN, supplier invoice..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select"
                        data-live-search-control>
                    <option value="">All</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.goods-receipts.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="receipt">
        {{ $receipts->total() }} {{ \Illuminate\Support\Str::plural('receipt', $receipts->total()) }}
    </x-admin.live-search-status>

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
            <tbody data-live-search-target>
                @include('admin.goods-receipts._row-template', ['receipts' => $receipts])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $receipts->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection