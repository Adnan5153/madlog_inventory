@extends('layouts.admin', ['title' => 'Lubricants'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Lubricants']]" />

    <x-admin.page-header title="Lubricants" subtitle="Engine oils, gear oils, transmission fluids, hydraulics, greases, coolants and other workshop lubricants.">
        <x-slot:actions>
            <a href="{{ route('admin.lubricants.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New lubricant
            </a>
            <a href="{{ route('admin.lubricant-stock-adjustments.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-sliders me-1"></i> Adjustments
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.lubricants.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.lubricants.search') }}">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}"
                       class="form-control"
                       placeholder="Name, code, SKU, barcode, brand, manufacturer, MPN…"
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="lubricant_type" class="form-label">Type</label>
                <select id="lubricant_type" name="lubricant_type" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($lubricantTypes as $type)
                        <option value="{{ $type->value }}" @selected($lubricantType === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="viscosity_grade" class="form-label">Viscosity</label>
                <select id="viscosity_grade" name="viscosity_grade" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($viscosities as $vis)
                        <option value="{{ $vis->value }}" @selected($viscosityGrade === $vis->value)>{{ $vis->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="application_type" class="form-label">Application</label>
                <select id="application_type" name="application_type" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($applications as $app)
                        <option value="{{ $app->value }}" @selected($applicationType === $app->value)>{{ $app->label() }}</option>
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
            <x-admin.clear-filters :route="route('admin.lubricants.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status>
        <span data-live-search-count>{{ $lubricants->total() }} {{ \Illuminate\Support\Str::plural('lubricant', $lubricants->total()) }}</span>
    </x-admin.live-search-status>

    <div class="admin-table table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Lubricant</th>
                    <th>Code</th>
                    <th>SKU</th>
                    <th>Type</th>
                    <th>Application</th>
                    <th>Package</th>
                    <th>Bin</th>
                    <th class="text-end">On hand</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.lubricants._row-template', ['lubricants' => $lubricants])
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <div data-live-search-pagination>
            {{ $lubricants->links('vendor.pagination.bootstrap-5') }}
        </div>
    </div>
@endsection