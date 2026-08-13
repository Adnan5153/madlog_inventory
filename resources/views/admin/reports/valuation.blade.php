@extends('layouts.admin', ['title' => 'Inventory valuation'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Reports', 'url' => null], ['label' => 'Inventory valuation']]" />

    <x-admin.page-header title="Inventory valuation" subtitle="Aggregated on-hand value across every bin in this workshop.">
        <x-slot:actions>
            <a href="{{ route('admin.reports.valuation.export') }}" class="btn btn-outline-secondary">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <x-admin.stat-card
                label="Inventory value"
                :value="number_format((float) $data['inventory_value'], 2).' '.$currency"
                icon="bi-cash-coin"
                variant="success" />
        </div>
        <div class="col-12 col-md-4">
            <x-admin.stat-card
                label="Distinct parts in stock"
                :value="number_format($data['parts_in_stock'])"
                icon="bi-box-seam" />
        </div>
        <div class="col-12 col-md-4">
            <x-admin.stat-card
                label="Inventory buckets"
                :value="number_format($data['items_count'])"
                icon="bi-stack"
                variant="info" />
        </div>
    </div>

    <div class="admin-card">
        <p class="text-muted mb-0">
            Numbers come from <code>SUM(quantity * cost_price)</code> over all inventory buckets in your workshop.
            Configure the cost basis per item from each bin's stock movements.
        </p>
    </div>
@endsection