@php
    $isEdit = !empty($order);
    $items = old('items', $isEdit
        ? $order->items->map(fn($i) => [
            'part_id' => $i->part_id,
            'quantity_ordered' => (string) $i->quantity_ordered,
            'unit_cost' => (string) $i->unit_cost,
        ])->toArray()
        : [['part_id' => '', 'quantity_ordered' => 1, 'unit_cost' => '0.00']]
    );
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $order?->workshop_id ?? auth()->user()?->workshop_id
    );
@endphp

<div class="admin-card">
    <div class="row g-3">
        @if ($isGlobalAdmin)
            <div class="col-md-6">
                <label for="workshop_id" class="form-label">
                    Workshop <span class="text-danger">*</span>
                </label>
                <select id="workshop_id" name="workshop_id" required
                        class="form-select @error('workshop_id') is-invalid @enderror">
                    <option value="">— Select a workshop —</option>
                    @foreach ($workshops as $workshop)
                        <option value="{{ $workshop->id }}"
                                @selected((int) $selectedWorkshopId === (int) $workshop->id)>
                            {{ $workshop->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">The workshop this purchase order belongs to.</div>
                @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif

        <div class="col-md-6">
            <label for="supplier_id" class="form-label">Supplier</label>
            <select id="supplier_id" name="supplier_id" class="form-select" required>
                <option value="">Select supplier...</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected((string) old('supplier_id', $isEdit ? $order->supplier_id : '') === (string) $s->id)>
                        {{ $s->name }}
                    </option>
                @endforeach
            </select>
            @error('supplier_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label for="order_date" class="form-label">Order date</label>
            <input type="date" id="order_date" name="order_date" class="form-control" value="{{ old('order_date', $isEdit ? $order->order_date?->format('Y-m-d') : date('Y-m-d')) }}" required>
            @error('order_date')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label for="expected_date" class="form-label">Expected date</label>
            <input type="date" id="expected_date" name="expected_date" class="form-control" value="{{ old('expected_date', $isEdit ? $order->expected_date?->format('Y-m-d') : '') }}">
            @error('expected_date')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="notes" class="form-label">Notes</label>
            <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $isEdit ? $order->notes : '') }}</textarea>
            @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="admin-card mt-3">
    <h2 class="h6 mb-3">Line items</h2>
    <div id="po-lines">
        @foreach($items as $idx => $line)
            <div class="row g-2 align-items-end po-line mb-2">
                <div class="col-md-5">
                    <label class="form-label small">Part</label>
                    <input type="number" name="items[{{ $idx }}][part_id]" class="form-control" value="{{ $line['part_id'] }}" placeholder="Part ID" required>
                    @error("items.$idx.part_id")<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Quantity</label>
                    <input type="number" step="0.01" name="items[{{ $idx }}][quantity_ordered]" class="form-control" value="{{ $line['quantity_ordered'] }}" min="0.01" required>
                    @error("items.$idx.quantity_ordered")<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Unit cost</label>
                    <input type="number" step="0.01" name="items[{{ $idx }}][unit_cost]" class="form-control" value="{{ $line['unit_cost'] }}" min="0" required>
                    @error("items.$idx.unit_cost")<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm po-remove" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" id="po-add" class="btn btn-outline-secondary btn-sm mt-2">
        <i class="bi bi-plus-lg me-1"></i> Add line
    </button>
</div>

{{-- PO line item add/remove behaviour lives in resources/js/app.js --}}
