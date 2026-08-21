@extends('layouts.admin', ['title' => $order->po_number])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Purchase orders', 'url' => route('admin.purchase-orders.index')],
        ['label' => $order->po_number],
    ]" />

    <x-admin.page-header :title="$order->po_number" :subtitle="$order->supplier?->name ?? ''">
        <x-slot:actions>
            @if(in_array($order->status, ['draft'], true))
                <a href="{{ route('admin.purchase-orders.edit', $order) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endif
            @if($order->status === 'draft')
                <form method="POST" action="{{ route('admin.purchase-orders.submit', $order) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-send me-1"></i> Submit for approval
                    </button>
                </form>
            @endif
            @if($order->status === 'submitted')
                <form method="POST" action="{{ route('admin.purchase-orders.approve', $order) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-outline-success">
                        <i class="bi bi-check2-circle me-1"></i> Approve
                    </button>
                </form>
            @endif
            @if(in_array($order->status, ['approved','partially_received'], true))
                <a href="{{ route('admin.purchase-orders.receive', $order) }}" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Receive goods
                </a>
            @endif
            @if($order->isCancellable())
                <form method="POST" action="{{ route('admin.purchase-orders.cancel', $order) }}" class="d-inline" data-confirm-form data-confirm="Cancel this purchase order?">
                    @csrf
                    <input type="hidden" name="reason" value="cancelled from UI">
                    <button class="btn btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">PO Number</dt><dd class="col-7">{{ $order->po_number }}</dd>
                    <dt class="col-5 text-muted">Supplier</dt><dd class="col-7">{{ $order->supplier?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Status</dt><dd class="col-7"><x-admin.status-badge :on="!in_array($order->status, ['cancelled','draft'])" :label="ucfirst(str_replace('_', ' ', $order->status))" /></dd>
                    <dt class="col-5 text-muted">Created by</dt><dd class="col-7">{{ $order->creator?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Approved by</dt><dd class="col-7">{{ $order->approver?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Order date</dt><dd class="col-7">{{ $order->order_date?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Expected</dt><dd class="col-7">{{ $order->expected_date?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Received</dt><dd class="col-7">{{ $order->received_date?->format('Y-m-d') ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="admin-card">
                <h2 class="h6 mb-3">Line items</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Part</th>
                                <th class="text-end">Ordered</th>
                                <th class="text-end">Received</th>
                                <th class="text-end">Remaining</th>
                                <th class="text-end">Unit cost</th>
                                <th class="text-end">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->part?->name ?? '—' }}
                                        <div class="text-muted small">{{ $item->part?->sku }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format($item->quantity_ordered, 2) }}</td>
                                    <td class="text-end">{{ number_format($item->quantity_received, 2) }}</td>
                                    <td class="text-end">{{ number_format($item->remainingQuantity(), 2) }}</td>
                                    <td class="text-end">${{ number_format($item->unit_cost, 2) }}</td>
                                    <td class="text-end">${{ number_format($item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Subtotal</th>
                                <th class="text-end">${{ number_format($order->subtotal, 2) }}</th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end">Tax</th>
                                <th class="text-end">${{ number_format($order->tax, 2) }}</th>
                            </tr>
                            <tr>
                                <th colspan="5" class="text-end">Total</th>
                                <th class="text-end">${{ number_format($order->total, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        @if($order->goodsReceipts->isNotEmpty())
            <div class="col-12">
                <div class="admin-card">
                    <h2 class="h6 mb-3">Goods receipts</h2>
                    <ul class="list-group list-group-flush">
                        @foreach($order->goodsReceipts as $grn)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>
                                    <a href="{{ route('admin.goods-receipts.show', $grn) }}" class="text-decoration-none">{{ $grn->grn_number }}</a>
                                    <span class="text-muted ms-2">{{ $grn->received_at?->format('Y-m-d H:i') }}</span>
                                </span>
                                <span><x-admin.status-badge :on="$grn->status !== 'disputed'" :label="ucfirst($grn->status)" /></span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endsection
