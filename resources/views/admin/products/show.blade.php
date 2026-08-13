@extends('layouts.admin', ['title' => $product->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Products', 'url' => route('admin.products.index')],
        ['label' => $product->name],
    ]" />

    <x-admin.page-header :title="$product->name" :subtitle="$product->sku ? 'SKU '.$product->sku : 'No SKU'">
        <x-slot:actions>
            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-warning">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Name</dt><dd class="col-8">{{ $product->name }}</dd>
                    <dt class="col-4 text-muted">SKU</dt><dd class="col-8">{{ $product->sku ?? '—' }}</dd>
                    <dt class="col-4 text-muted">OEM</dt><dd class="col-8">{{ $product->oem_part_number ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Barcode</dt><dd class="col-8">{{ $product->barcode ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Category</dt><dd class="col-8">{{ $product->category?->name ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Brand</dt><dd class="col-8">{{ $product->brand?->name ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Unit</dt><dd class="col-8">{{ $product->unit?->short_code ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Status</dt><dd class="col-8"><x-admin.status-badge :on="$product->is_active" /></dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Pricing & reorder</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Cost price</dt><dd class="col-8">{{ number_format((float) $product->cost_price, 2) }}</dd>
                    <dt class="col-4 text-muted">Sale price</dt><dd class="col-8">{{ number_format((float) $product->sale_price, 2) }}</dd>
                    <dt class="col-4 text-muted">Margin</dt>
                    <dd class="col-8">
                        @php
                            $cost = (float) $product->cost_price;
                            $sale = (float) $product->sale_price;
                            $margin = $cost > 0 ? (($sale - $cost) / $cost) * 100 : 0;
                        @endphp
                        {{ number_format($margin, 1) }} %
                    </dd>
                    <dt class="col-4 text-muted">Reorder ≤</dt><dd class="col-8">{{ number_format($product->reorder_threshold) }}</dd>
                    <dt class="col-4 text-muted">Reorder qty</dt><dd class="col-8">{{ number_format($product->reorder_quantity) }}</dd>
                    <dt class="col-4 text-muted">On hand</dt>
                    <dd class="col-8">
                        @php $oh = (float) ($product->on_hand ?? 0); @endphp
                        <span class="{{ $oh <= (float) $product->reorder_threshold ? 'text-danger fw-semibold' : '' }}">{{ number_format($oh, 2) }}</span>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="admin-card">
                <h2 class="h6 mb-3">Recent stock movements</h2>
                <div class="admin-table">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Type</th>
                                <th>Bin</th>
                                <th class="text-end">Quantity</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $m)
                                <tr>
                                    <td class="text-muted small">{{ $m->occurred_at?->format('Y-m-d H:i') ?? $m->created_at->format('Y-m-d H:i') }}</td>
                                    <td><x-admin.status-badge variant="info">{{ $m->type }}</x-admin.status-badge></td>
                                    <td>{{ $m->bin?->code ?? '—' }}</td>
                                    <td class="text-end {{ (float) $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $m->quantity, 2) }}</td>
                                    <td class="text-muted">{{ $m->user?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"><x-admin.empty-state icon="bi-arrow-left-right" title="No movements yet" /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection