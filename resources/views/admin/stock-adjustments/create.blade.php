@extends('layouts.admin', ['title' => 'New stock adjustment'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Stock adjustments', 'url' => route('admin.stock-adjustments.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New stock adjustment" subtitle="Each line references an existing InventoryItem bucket. Approval rules come from settings." />

    <form method="POST" action="{{ route('admin.stock-adjustments.store') }}">
        @csrf

        <div class="admin-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="reason" class="form-label">Reason</label>
                    <select id="reason" name="reason" class="form-select" required>
                        @foreach(['cycle_count','shrinkage','damage','found','manual'] as $r)
                            <option value="{{ $r }}" @selected(old('reason') === $r)>{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                        @endforeach
                    </select>
                    @error('reason')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-9">
                    <label for="notes" class="form-label">Notes</label>
                    <input type="text" id="notes" name="notes" class="form-control" value="{{ old('notes') }}">
                    @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Lines</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Inventory item</th>
                            <th class="text-end">Adjustment qty</th>
                            <th class="text-end">Unit cost</th>
                        </tr>
                    </thead>
                    <tbody id="adj-lines">
                        @for($i = 0; $i < 1; $i++)
                            <tr>
                                <td>
                                    <input type="number" name="items[{{ $i }}][inventory_item_id]" class="form-control form-control-sm" placeholder="InventoryItem ID" required>
                                    @error("items.$i.inventory_item_id")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="items[{{ $i }}][adjustment_quantity]" class="form-control form-control-sm text-end" value="{{ old("items.$i.adjustment_quantity") }}" required>
                                    @error("items.$i.adjustment_quantity")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_cost]" class="form-control form-control-sm text-end" value="{{ old("items.$i.unit_cost") }}">
                                    @error("items.$i.unit_cost")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            <button type="button" id="adj-add" class="btn btn-outline-secondary btn-sm mt-2">
                <i class="bi bi-plus-lg me-1"></i> Add line
            </button>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Save adjustment
            </button>
            <a href="{{ route('admin.stock-adjustments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const tbody = document.getElementById('adj-lines');
            const add = document.getElementById('adj-add');
            if (!tbody || !add) return;
            add.addEventListener('click', () => {
                const idx = tbody.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="number" name="items[${idx}][inventory_item_id]" class="form-control form-control-sm" placeholder="InventoryItem ID" required></td>
                    <td><input type="number" step="0.01" name="items[${idx}][adjustment_quantity]" class="form-control form-control-sm text-end" required></td>
                    <td><input type="number" step="0.01" min="0" name="items[${idx}][unit_cost]" class="form-control form-control-sm text-end"></td>
                `;
                tbody.appendChild(tr);
            });
        })();
    </script>
@endsection
