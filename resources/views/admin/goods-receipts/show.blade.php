@extends('layouts.admin', ['title' => $receipt->grn_number])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Goods receipts', 'url' => route('admin.goods-receipts.index')],
        ['label' => $receipt->grn_number],
    ]" />

    <x-admin.page-header :title="$receipt->grn_number" :subtitle="$receipt->purchaseOrder?->po_number ?? ''">
        <x-slot:actions>
            @if($receipt->purchaseOrder)
                <a href="{{ route('admin.purchase-orders.show', $receipt->purchaseOrder) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> View PO
                </a>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">GRN</dt><dd class="col-7">{{ $receipt->grn_number }}</dd>
                    <dt class="col-5 text-muted">PO</dt><dd class="col-7">{{ $receipt->purchaseOrder?->po_number ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Supplier</dt><dd class="col-7">{{ $receipt->purchaseOrder?->supplier?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Status</dt><dd class="col-7"><x-admin.status-badge :on="$receipt->status !== 'disputed'" :label="ucfirst($receipt->status)" /></dd>
                    <dt class="col-5 text-muted">Received by</dt><dd class="col-7">{{ $receipt->receiver?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Received at</dt><dd class="col-7">{{ $receipt->received_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Supplier invoice</dt><dd class="col-7">{{ $receipt->supplier_invoice_number ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="admin-card">
                <h2 class="h6 mb-3">Lines</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Part</th>
                                <th class="text-end">Ordered</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Damaged</th>
                                <th>Bin</th>
                                <th>Batch</th>
                                <th class="text-end">Unit cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receipt->items as $item)
                                <tr>
                                    <td>{{ $item->part?->name ?? '—' }}<div class="text-muted small">{{ $item->part?->sku }}</div></td>
                                    <td class="text-end">{{ number_format($item->quantity_ordered, 2) }}</td>
                                    <td class="text-end">{{ number_format($item->quantity_received, 2) }}</td>
                                    <td class="text-end">{{ number_format($item->damaged_quantity, 2) }}</td>
                                    <td>{{ $item->binLocation?->code ?? '—' }}</td>
                                    <td>{{ $item->batch_number ?? '—' }}</td>
                                    <td class="text-end">${{ number_format($item->unit_cost, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
