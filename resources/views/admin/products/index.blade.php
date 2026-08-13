@extends('layouts.admin', ['title' => 'Products'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Products']]" />

    <x-admin.page-header title="Products" subtitle="Parts catalog. Workshop-scoped pricing and reorder policy.">
        <x-slot:actions>
            <a href="{{ route('admin.products.create') }}" class="btn btn-warning">
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
        <form method="GET" action="{{ route('admin.products.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Name, SKU, OEM, barcode...">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select">
                    <option value="">All</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="brand_id" class="form-label">Brand</label>
                <select id="brand_id" name="brand_id" class="form-select">
                    <option value="">All</option>
                    @foreach($brands as $b)
                        <option value="{{ $b->id }}" @selected($brandId == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2 col-lg-2">
                <label for="active" class="form-label">Status</label>
                <select id="active" name="active" class="form-select">
                    <option value="">All</option>
                    <option value="yes" @selected($active === 'yes')>Active</option>
                    <option value="no"  @selected($active === 'no')>Inactive</option>
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
                    <th>Name</th>
                    <th>SKU / Barcode</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">Sale</th>
                    <th class="text-end">On hand</th>
                    <th class="text-end">Reorder ≤</th>
                    <th>Status</th>
                    <th class="text-end" style="width: 180px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($parts as $p)
                    <tr>
                        <td>
                            <a href="{{ route('admin.products.show', $p) }}" class="text-decoration-none">
                                {{ $p->name }}
                            </a>
                            @if($p->oem_part_number)
                                <div class="small text-muted">OEM {{ $p->oem_part_number }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $p->sku ?? '—' }}</div>
                            <div class="small text-muted">{{ $p->barcode ?? '—' }}</div>
                        </td>
                        <td>{{ $p->category?->name ?? '—' }}</td>
                        <td>{{ $p->brand?->name ?? '—' }}</td>
                        <td class="text-end">{{ number_format((float) $p->cost_price, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $p->sale_price, 2) }}</td>
                        <td class="text-end">
                            @php $oh = (float) ($p->on_hand ?? 0); @endphp
                            <span class="{{ $oh <= (float) $p->reorder_threshold ? 'text-danger fw-semibold' : '' }}">
                                {{ number_format($oh, 2) }}
                            </span>
                        </td>
                        <td class="text-end">{{ number_format($p->reorder_threshold) }}</td>
                        <td>
                            <x-admin.status-badge :on="$p->is_active" />
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.products.show', $p) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ route('admin.products.edit', $p) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.products.destroy', $p) }}" class="d-inline" data-confirm-form data-confirm="Delete this product?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <x-admin.empty-state icon="bi-box-seam" title="No products yet">
                                Add your first part to start tracking inventory.
                            </x-admin.empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $parts->links('vendor.pagination.bootstrap-5') }}
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
                        Required columns: <code>sku, name, cost_price, sale_price</code>. Optional:
                        <code>oem_part_number, barcode, description, category, brand, unit,
                        reorder_threshold, reorder_quantity, is_active</code>.
                    </p>
                    <input type="file" name="file" accept=".csv,.txt" required class="form-control">
                    @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
                    @error('import')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-warning">Upload</button>
                </div>
            </form>
        </div>
    </div>
@endsection