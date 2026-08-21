@extends('layouts.admin', ['title' => 'Receive ' . $order->po_number])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Purchase orders', 'url' => route('admin.purchase-orders.index')],
        ['label' => $order->po_number, 'url' => route('admin.purchase-orders.show', $order)],
        ['label' => 'Receive'],
    ]" />

    <x-admin.page-header :title="'Receive goods — ' . $order->po_number" :subtitle="$order->supplier?->name ?? ''" />

    <form method="POST" action="{{ route('admin.purchase-orders.receive', $order) }}">
        @csrf

        <div class="admin-card">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="bin_location_id" class="form-label">Default destination bin</label>
                    <input type="number" id="bin_location_id" name="bin_location_id" class="form-control" value="{{ old('bin_location_id') }}" placeholder="Bin ID">
                    @error('bin_location_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="supplier_invoice_number" class="form-label">Supplier invoice #</label>
                    <input type="text" id="supplier_invoice_number" name="supplier_invoice_number" class="form-control" value="{{ old('supplier_invoice_number') }}">
                    @error('supplier_invoice_number')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label for="notes" class="form-label">Notes</label>
                    <input type="text" id="notes" name="notes" class="form-control" value="{{ old('notes') }}">
                    @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Receipt lines</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>PO line</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Already received</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-end">Receive</th>
                            <th class="text-end">Damaged</th>
                            <th>Bin</th>
                            <th>Batch</th>
                            <th>Unit cost</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->items as $i => $item)
                            <tr>
                                <td>
                                    {{ $item->part?->name ?? 'Part #' . $item->part_id }}
                                    <div class="text-muted small">#{{ $item->id }}</div>
                                    <input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $item->id }}">
                                </td>
                                <td class="text-end">{{ number_format($item->quantity_ordered, 2) }}</td>
                                <td class="text-end">{{ number_format($item->quantity_received, 2) }}</td>
                                <td class="text-end">{{ number_format($item->remainingQuantity(), 2) }}</td>
                                <td class="text-end" style="width: 110px;">
                                    <input type="number" step="0.01" min="0" max="{{ $item->remainingQuantity() }}" name="items[{{ $i }}][quantity_received]" class="form-control form-control-sm text-end" value="{{ old("items.$i.quantity_received", $item->remainingQuantity()) }}">
                                </td>
                                <td class="text-end" style="width: 90px;">
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][damaged_quantity]" class="form-control form-control-sm text-end" value="{{ old("items.$i.damaged_quantity", 0) }}">
                                </td>
                                <td style="width: 90px;">
                                    <input type="number" name="items[{{ $i }}][bin_location_id]" class="form-control form-control-sm" value="{{ old("items.$i.bin_location_id") }}" placeholder="Bin ID">
                                </td>
                                <td style="width: 120px;">
                                    <input type="text" name="items[{{ $i }}][batch_number]" class="form-control form-control-sm" value="{{ old("items.$i.batch_number") }}">
                                </td>
                                <td style="width: 110px;">
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_cost]" class="form-control form-control-sm" value="{{ old("items.$i.unit_cost", (string) $item->unit_cost) }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary">
                <i class="bi bi-box-arrow-in-down me-1"></i> Record receipt
            </button>
            <a href="{{ route('admin.purchase-orders.show', $order) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
