@extends('layouts.admin', ['title' => 'New battery stock adjustment'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Battery stock adjustments', 'url' => route('admin.battery-stock-adjustments.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New battery stock adjustment" subtitle="Adjust on-hand quantity for one or more batteries. Approval writes the ledger." />

    <form method="POST" action="{{ route('admin.battery-stock-adjustments.store') }}">
        @csrf

        <div class="admin-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="reference" class="form-label">Reference</label>
                    <input id="reference" type="text" name="reference" required maxlength="32"
                           value="{{ old('reference', 'BSA-'.date('Y').'-'.strtoupper(substr(md5(uniqid()), 0, 6))) }}"
                           class="form-control @error('reference') is-invalid @enderror">
                    @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label for="reason" class="form-label">Reason</label>
                    <select id="reason" name="reason" class="form-select" required>
                        @foreach(['cycle_count','shrinkage','damage','found','manual'] as $r)
                            <option value="{{ $r }}" @selected(old('reason', 'manual') === $r)>{{ ucfirst(str_replace('_', ' ', $r)) }}</option>
                        @endforeach
                    </select>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label for="notes" class="form-label">Notes</label>
                    <input id="notes" type="text" name="notes" class="form-control"
                           value="{{ old('notes') }}">
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Lines</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Battery</th>
                            <th>Bin</th>
                            <th class="text-end">Counted qty</th>
                            <th class="text-end">Delta qty</th>
                            <th class="text-end">Unit cost</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody id="bsa-lines">
                        @for($i = 0; $i < 1; $i++)
                            <tr>
                                <td>
                                    <select name="items[{{ $i }}][battery_id]" class="form-select form-select-sm" required>
                                        <option value="">— Battery —</option>
                                        @foreach($batteries as $b)
                                            <option value="{{ $b->id }}" @selected(old("items.$i.battery_id") == $b->id)>{{ $b->name }} ({{ $b->battery_code }})</option>
                                        @endforeach
                                    </select>
                                    @error("items.$i.battery_id")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <select name="items[{{ $i }}][bin_id]" class="form-select form-select-sm">
                                        <option value="">— Bin —</option>
                                        @foreach($binLocations as $bin)
                                            <option value="{{ $bin->id }}" @selected(old("items.$i.bin_id") == $bin->id)>{{ $bin->code }}{{ $bin->zone ? ' · '.$bin->zone : '' }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.$i.bin_id")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="items[{{ $i }}][counted_quantity]"
                                           value="{{ old("items.$i.counted_quantity") }}"
                                           class="form-control form-control-sm text-end" required>
                                    @error("items.$i.counted_quantity")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="items[{{ $i }}][quantity]"
                                           value="{{ old("items.$i.quantity") }}"
                                           class="form-control form-control-sm text-end" required>
                                    @error("items.$i.quantity")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_cost]"
                                           value="{{ old("items.$i.unit_cost") }}"
                                           class="form-control form-control-sm text-end">
                                    @error("items.$i.unit_cost")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                                <td>
                                    <input type="text" name="items[{{ $i }}][reason]" maxlength="255"
                                           value="{{ old("items.$i.reason") }}"
                                           class="form-control form-control-sm">
                                    @error("items.$i.reason")<div class="text-danger small">{{ $message }}</div>@enderror
                                </td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
            <button type="button" id="bsa-add" class="btn btn-outline-secondary btn-sm mt-2">
                <i class="bi bi-plus-lg me-1"></i> Add line
            </button>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Save adjustment
            </button>
            <a href="{{ route('admin.battery-stock-adjustments.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const tbody = document.getElementById('bsa-lines');
            const add = document.getElementById('bsa-add');
            if (!tbody || !add) return;

            const batteries = @json($batteries->map(fn($b) => ['id' => $b->id, 'label' => $b->name.' ('.$b->battery_code.')'])->values());
            const bins = @json($binLocations->map(fn($bin) => ['id' => $bin->id, 'label' => $bin->code.($bin->zone ? ' · '.$bin->zone : '')])->values());

            const batteryOptions = batteries.map(b => `<option value="${b.id}">${b.label}</option>`).join('');
            const binOptions = bins.map(b => `<option value="${b.id}">${b.label}</option>`).join('');

            add.addEventListener('click', () => {
                const idx = tbody.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><select name="items[${idx}][battery_id]" class="form-select form-select-sm" required>
                        <option value="">— Battery —</option>${batteryOptions}
                    </select></td>
                    <td><select name="items[${idx}][bin_id]" class="form-select form-select-sm">
                        <option value="">— Bin —</option>${binOptions}
                    </select></td>
                    <td><input type="number" step="0.01" name="items[${idx}][counted_quantity]" class="form-control form-control-sm text-end" required></td>
                    <td><input type="number" step="0.01" name="items[${idx}][quantity]" class="form-control form-control-sm text-end" required></td>
                    <td><input type="number" step="0.01" min="0" name="items[${idx}][unit_cost]" class="form-control form-control-sm text-end"></td>
                    <td><input type="text" name="items[${idx}][reason]" maxlength="255" class="form-control form-control-sm"></td>
                `;
                tbody.appendChild(tr);
            });
        })();
    </script>
@endsection
