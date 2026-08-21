@extends('layouts.admin', ['title' => 'Products'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Products']]" />

    <x-admin.page-header title="Products" subtitle="Parts catalog. Workshop-scoped inventory and reorder policy.">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New product
            </a>
            <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="bi bi-upload me-1"></i> Import CSV
            </button>
            <a href="{{ route('admin.products.export') }}" class="btn btn-outline-secondary">
                <i class="bi bi-download me-1"></i> Export
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.products.index') }}"
              class="row g-2 flex-grow-1"
              id="products-filter-form"
              data-live-search
              data-search-url="{{ route('admin.products.search') }}">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}"
                       class="form-control"
                       placeholder="Name, SKU, OEM, barcode, brand, category…"
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
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
            <div class="col-6 col-md-2 col-lg-2">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="sort" class="form-label">Sort by</label>
                <select id="sort" name="sort" class="form-select" data-live-search-control>
                    <option value="name_asc"     @selected($sort === 'name_asc')>Name (A → Z)</option>
                    <option value="name_desc"    @selected($sort === 'name_desc')>Name (Z → A)</option>
                    <option value="recent"       @selected($sort === 'recent')>Most recent</option>
                    <option value="oldest"       @selected($sort === 'oldest')>Oldest first</option>
                    <option value="cost_asc"     @selected($sort === 'cost_asc')>Cost (low → high)</option>
                    <option value="cost_desc"    @selected($sort === 'cost_desc')>Cost (high → low)</option>
                    <option value="reorder_asc"  @selected($sort === 'reorder_asc')>Reorder ≤ (low → high)</option>
                    <option value="reorder_desc" @selected($sort === 'reorder_desc')>Reorder ≤ (high → low)</option>
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.products.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status>
        <span data-live-search-count>{{ $parts->total() }} {{ \Illuminate\Support\Str::plural('product', $parts->total()) }}</span>
    </x-admin.live-search-status>

    <div class="admin-table table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>OEM #</th>
                    <th>Barcode</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Brand</th>
                    <th>Location</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">On hand</th>
                    <th class="text-end">Reorder ≤</th>
                    <th class="text-end">Reorder qty</th>
                    <th>Status</th>
                    <th>Entry date</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                {{-- Server-rendered first page; the live-search JS will
                     replace the contents of this <tbody> on every filter
                     change. The same row template is reused by the JSON
                     endpoint (admin.products.search). --}}
                @include('admin.products._row-template', ['parts' => $parts])
            </tbody>
        </table>
    </div>

    <div class="mt-3 d-flex justify-content-between align-items-center">
        <div data-live-search-pagination>
            {{ $parts->links('vendor.pagination.bootstrap-5') }}
        </div>
    </div>

    {{-- Import modal --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.products.import') }}" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Import products from CSV</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Required columns: <code>sku, name, cost_price</code>. Optional:
                        <code>oem_part_number, barcode, description, category, brand, unit,
                        reorder_threshold, reorder_quantity, is_active</code>.
                    </p>
                    <input type="file" name="file" accept=".csv,.txt" required class="form-control">
                    @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
                    @error('import')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
@endsection