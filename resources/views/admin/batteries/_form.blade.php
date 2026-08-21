@php
    use App\Enums\BatteryChemistry;
    use App\Enums\BatteryStatus;
    use App\Enums\BatteryCondition;
    use App\Enums\BatteryApplication;
@endphp

@props([
    'battery' => null,
    'workshops' => collect(),
    'pickedWorkshopId' => null,
    'binLocations' => collect(),
    'suppliers' => collect(),
    'chemistries' => [],
    'statuses' => [],
])

@php
    $isEdit = $battery !== null;
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $battery?->workshop_id ?? $pickedWorkshopId ?? auth()->user()?->workshop_id
    );
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-8">
        @if ($isGlobalAdmin)
            <div class="admin-card">
                <h2 class="h6 mb-3">Workshop</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="workshop_id" class="form-label">
                            Workshop <span class="text-danger">*</span>
                        </label>
                        <select id="workshop_id" name="workshop_id" required
                                class="form-select @error('workshop_id') is-invalid @enderror"
                                onchange="if (this.value) { window.location.assign('{{ route('admin.batteries.create') }}?workshop_id=' + encodeURIComponent(this.value)); }">
                            <option value="">— Select a workshop —</option>
                            @foreach ($workshops as $workshop)
                                <option value="{{ $workshop->id }}"
                                        @selected((int) $selectedWorkshopId === (int) $workshop->id)>
                                    {{ $workshop->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">The workshop this battery belongs to.</div>
                        @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        @endif

        <div class="admin-card {{ $isGlobalAdmin ? 'mt-3' : '' }}">
            <h2 class="h6 mb-3">Identification</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="battery_code" class="form-label">Battery code <span class="text-danger">*</span></label>
                    <input id="battery_code" type="text" name="battery_code" required maxlength="64"
                           value="{{ old('battery_code', $battery?->battery_code) }}"
                           class="form-control @error('battery_code') is-invalid @enderror"
                           placeholder="e.g. BTY-001">
                    @error('battery_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="sku" class="form-label">SKU</label>
                    <input id="sku" type="text" name="sku" maxlength="64"
                           value="{{ old('sku', $battery?->sku) }}"
                           class="form-control @error('sku') is-invalid @enderror">
                    @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input id="name" type="text" name="name" required maxlength="160"
                           value="{{ old('name', $battery?->name) }}"
                           class="form-control @error('name') is-invalid @enderror"
                           placeholder="e.g. Battery 12V 60Ah">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="barcode" class="form-label">Barcode</label>
                    <input id="barcode" type="text" name="barcode" maxlength="64"
                           value="{{ old('barcode', $battery?->barcode) }}"
                           class="form-control @error('barcode') is-invalid @enderror">
                    @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="brand" class="form-label">Brand</label>
                    <input id="brand" type="text" name="brand" maxlength="120"
                           value="{{ old('brand', $battery?->brand) }}"
                           class="form-control @error('brand') is-invalid @enderror"
                           placeholder="e.g. Bosch, Yuasa, Varta">
                    @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="manufacturer_part_number" class="form-label">Manufacturer part #</label>
                    <input id="manufacturer_part_number" type="text" name="manufacturer_part_number" maxlength="64"
                           value="{{ old('manufacturer_part_number', $battery?->manufacturer_part_number) }}"
                           class="form-control @error('manufacturer_part_number') is-invalid @enderror">
                    @error('manufacturer_part_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="5000"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $battery?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Classification</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="battery_type" class="form-label">Chemistry <span class="text-danger">*</span></label>
                    <select id="battery_type" name="battery_type" required
                            class="form-select @error('battery_type') is-invalid @enderror">
                        @foreach($chemistries as $chem)
                            <option value="{{ $chem->value }}" @selected(old('battery_type', $battery?->battery_type ?? 'lead_acid') === $chem->value)>{{ $chem->label() }}</option>
                        @endforeach
                    </select>
                    @error('battery_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="application_type" class="form-label">Application</label>
                    <select id="application_type" name="application_type"
                            class="form-select @error('application_type') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach(BatteryApplication::cases() as $app)
                            <option value="{{ $app->value }}" @selected(old('application_type', $battery?->application_type) === $app->value)>{{ $app->label() }}</option>
                        @endforeach
                    </select>
                    @error('application_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="condition" class="form-label">Condition <span class="text-danger">*</span></label>
                    <select id="condition" name="condition" required
                            class="form-select @error('condition') is-invalid @enderror">
                        @foreach(BatteryCondition::cases() as $cond)
                            <option value="{{ $cond->value }}" @selected(old('condition', $battery?->condition ?? 'new') === $cond->value)>{{ $cond->label() }}</option>
                        @endforeach
                    </select>
                    @error('condition')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select id="status" name="status" required
                            class="form-select @error('status') is-invalid @enderror">
                        @foreach($statuses as $st)
                            <option value="{{ $st->value }}" @selected(old('status', $battery?->status ?? 'active') === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Technical specifications</h2>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label for="voltage" class="form-label">Voltage (V) <span class="text-danger">*</span></label>
                    <input id="voltage" type="number" step="0.01" min="0" name="voltage" required
                           value="{{ old('voltage', $battery?->voltage) }}"
                           class="form-control @error('voltage') is-invalid @enderror">
                    @error('voltage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="capacity_ah" class="form-label">Capacity (Ah) <span class="text-danger">*</span></label>
                    <input id="capacity_ah" type="number" step="0.01" min="0" name="capacity_ah" required
                           value="{{ old('capacity_ah', $battery?->capacity_ah) }}"
                           class="form-control @error('capacity_ah') is-invalid @enderror">
                    @error('capacity_ah')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="cold_cranking_amps" class="form-label">CCA</label>
                    <input id="cold_cranking_amps" type="number" step="1" min="0" name="cold_cranking_amps"
                           value="{{ old('cold_cranking_amps', $battery?->cold_cranking_amps) }}"
                           class="form-control @error('cold_cranking_amps') is-invalid @enderror">
                    @error('cold_cranking_amps')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="reserve_capacity" class="form-label">RC (min)</label>
                    <input id="reserve_capacity" type="number" step="1" min="0" name="reserve_capacity"
                           value="{{ old('reserve_capacity', $battery?->reserve_capacity) }}"
                           class="form-control @error('reserve_capacity') is-invalid @enderror">
                    @error('reserve_capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="terminal_type" class="form-label">Terminal</label>
                    <input id="terminal_type" type="text" name="terminal_type" maxlength="32"
                           value="{{ old('terminal_type', $battery?->terminal_type) }}"
                           class="form-control @error('terminal_type') is-invalid @enderror"
                           placeholder="top, side, stud, flag">
                    @error('terminal_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="polarity" class="form-label">Polarity</label>
                    <select id="polarity" name="polarity"
                            class="form-select @error('polarity') is-invalid @enderror">
                        <option value="">— None —</option>
                        <option value="positive" @selected(old('polarity', $battery?->polarity) === 'positive')>Positive</option>
                        <option value="negative" @selected(old('polarity', $battery?->polarity) === 'negative')>Negative</option>
                    </select>
                    @error('polarity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-3">
                    <label for="length_mm" class="form-label">Length (mm)</label>
                    <input id="length_mm" type="number" step="0.01" min="0" name="length_mm"
                           value="{{ old('length_mm', $battery?->length_mm) }}"
                           class="form-control @error('length_mm') is-invalid @enderror">
                    @error('length_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-3">
                    <label for="width_mm" class="form-label">Width (mm)</label>
                    <input id="width_mm" type="number" step="0.01" min="0" name="width_mm"
                           value="{{ old('width_mm', $battery?->width_mm) }}"
                           class="form-control @error('width_mm') is-invalid @enderror">
                    @error('width_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-3">
                    <label for="height_mm" class="form-label">Height (mm)</label>
                    <input id="height_mm" type="number" step="0.01" min="0" name="height_mm"
                           value="{{ old('height_mm', $battery?->height_mm) }}"
                           class="form-control @error('height_mm') is-invalid @enderror">
                    @error('height_mm')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-3">
                    <label for="weight_kg" class="form-label">Weight (kg)</label>
                    <input id="weight_kg" type="number" step="0.001" min="0" name="weight_kg"
                           value="{{ old('weight_kg', $battery?->weight_kg) }}"
                           class="form-control @error('weight_kg') is-invalid @enderror">
                    @error('weight_kg')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Inventory & pricing</h2>
            <div class="row g-3">
                <div class="col-6 col-md-4">
                    <label for="cost_price" class="form-label">Cost price <span class="text-danger">*</span></label>
                    <input id="cost_price" type="number" step="0.01" min="0" name="cost_price" required
                           value="{{ old('cost_price', $battery?->cost_price ?? 0) }}"
                           class="form-control @error('cost_price') is-invalid @enderror">
                    @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="bin_location_id" class="form-label">Bin location</label>
                    <select id="bin_location_id" name="bin_location_id"
                            class="form-select @error('bin_location_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($binLocations as $bin)
                            <option value="{{ $bin->id }}"
                                    @selected(old('bin_location_id', $battery?->bin_location_id) == $bin->id)>
                                {{ $bin->code }}{{ $bin->zone ? ' · '.$bin->zone : '' }}{{ $bin->aisle ? ' · '.$bin->aisle : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('bin_location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label for="reorder_threshold" class="form-label">Reorder threshold <span class="text-danger">*</span></label>
                    <input id="reorder_threshold" type="number" step="1" min="0" name="reorder_threshold" required
                           value="{{ old('reorder_threshold', $battery?->reorder_threshold ?? 0) }}"
                           class="form-control @error('reorder_threshold') is-invalid @enderror">
                    <div class="form-text">When on-hand drops to or below this value, the SKU is flagged low.</div>
                    @error('reorder_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label for="reorder_quantity" class="form-label">Reorder quantity <span class="text-danger">*</span></label>
                    <input id="reorder_quantity" type="number" step="1" min="0" name="reorder_quantity" required
                           value="{{ old('reorder_quantity', $battery?->reorder_quantity ?? 0) }}"
                           class="form-control @error('reorder_quantity') is-invalid @enderror">
                    @error('reorder_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select id="supplier_id" name="supplier_id"
                            class="form-select @error('supplier_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                    @selected(old('supplier_id', $battery?->supplier_id) == $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Primary supplier for this battery.</div>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Warranty</h2>
            <div class="row g-3">
                <div class="col-6 col-md-6">
                    <label for="warranty_period_months" class="form-label">Warranty period (months)</label>
                    <input id="warranty_period_months" type="number" min="0" max="120" name="warranty_period_months"
                           value="{{ old('warranty_period_months', $battery?->warranty_period_months) }}"
                           class="form-control @error('warranty_period_months') is-invalid @enderror">
                    @error('warranty_period_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-6">
                    <label for="warranty_expiry" class="form-label">Warranty expiry</label>
                    <input id="warranty_expiry" type="date" name="warranty_expiry"
                           value="{{ old('warranty_expiry', $battery?->warranty_expiry?->format('Y-m-d')) }}"
                           class="form-control @error('warranty_expiry') is-invalid @enderror">
                    @error('warranty_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Notes</h2>
            <textarea id="notes" name="notes" rows="3"
                      class="form-control @error('notes') is-invalid @enderror"
                      placeholder="Internal notes about this battery…">{{ old('notes', $battery?->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card">
            <h2 class="h6 mb-3">Catalog state</h2>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       @checked(old('is_active', $battery?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="form-text">Inactive batteries stay in the catalog but are hidden from operational dropdowns.</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.batteries.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create battery' }}</button>
</div>
