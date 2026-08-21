@extends('layouts.admin', ['title' => 'Batteries'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Batteries']]" />

    <x-admin.page-header title="Batteries" subtitle="Battery SKUs by chemistry, application, dimensions and warranty.">
        <x-slot:actions>
            <a href="{{ route('admin.batteries.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New battery
            </a>
            <a href="{{ route('admin.battery-stock-adjustments.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i> Adjustments
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.batteries.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.batteries.search') }}">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}"
                       class="form-control"
                       placeholder="Name, code, SKU, barcode, brand, MPN…"
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="battery_type" class="form-label">Chemistry</label>
                <select id="battery_type" name="battery_type" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($chemistries as $chem)
                        <option value="{{ $chem->value }}" @selected($batteryType === $chem->value)>{{ $chem->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="brand" class="form-label">Brand</label>
                <input type="text" id="brand" name="brand" value="{{ $brand }}"
                       class="form-control"
                       placeholder="Any brand"
                       autocomplete="off"
                       data-live-search-control>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="supplier_id" class="form-label">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected($supplierId == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="stock_status" class="form-label">Stock</label>
                <select id="stock_status" name="stock_status" class="form-select" data-live-search-control>
                    <option value="">Any</option>
                    @foreach(\App\Enums\StockStatus::cases() as $ss)
                        <option value="{{ $ss->value }}" @selected($stockStatus === $ss->value)>{{ $ss->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.batteries.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status>
        <span data-live-search-count>{{ $batteries->total() }} {{ \Illuminate\Support\Str::plural('battery', $batteries->total()) }}</span>
    </x-admin.live-search-status>

    <div class="admin-table table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Battery</th>
                    <th>Battery code</th>
                    <th>SKU</th>
                    <th>Chemistry</th>
                    <th>Voltage</th>
                    <th>Capacity (Ah)</th>
                    <th>Brand</th>
                    <th>Supplier</th>
                    <th>Bin</th>
                    <th class="text-end">On hand</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.batteries._row-template', ['batteries' => $batteries])
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <div data-live-search-pagination>
            {{ $batteries->links('vendor.pagination.bootstrap-5') }}
        </div>
    </div>
@endsection
