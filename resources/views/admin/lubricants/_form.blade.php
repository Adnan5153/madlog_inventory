@php
    use App\Enums\LubricantApplication;
    use App\Enums\LubricantPackageType;
    use App\Enums\LubricantStatus;
    use App\Enums\LubricantType;
    use App\Enums\LubricantViscosity;
@endphp

@props([
    'lubricant' => null,
    'workshops' => collect(),
    'pickedWorkshopId' => null,
    'binLocations' => collect(),
    'suppliers' => collect(),
    'lubricantTypes' => [],
    'viscosities' => [],
    'applications' => [],
    'packageTypes' => [],
    'statuses' => [],
])

@php
    $isEdit = $lubricant !== null;
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $lubricant?->workshop_id ?? $pickedWorkshopId ?? auth()->user()?->workshop_id
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
                                onchange="if (this.value) { window.location.assign('{{ route('admin.lubricants.create') }}?workshop_id=' + encodeURIComponent(this.value)); }">
                            <option value="">— Select a workshop —</option>
                            @foreach ($workshops as $workshop)
                                <option value="{{ $workshop->id }}"
                                        @selected((int) $selectedWorkshopId === (int) $workshop->id)>
                                    {{ $workshop->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">The workshop this lubricant belongs to.</div>
                        @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        @endif

        <div class="admin-card {{ $isGlobalAdmin ? 'mt-3' : '' }}">
            <h2 class="h6 mb-3">Identification</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="lubricant_code" class="form-label">Lubricant code <span class="text-danger">*</span></label>
                    <input id="lubricant_code" type="text" name="lubricant_code" required maxlength="64"
                           value="{{ old('lubricant_code', $lubricant?->lubricant_code) }}"
                           class="form-control @error('lubricant_code') is-invalid @enderror"
                           placeholder="e.g. LUB-001">
                    @error('lubricant_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="sku" class="form-label">SKU</label>
                    <input id="sku" type="text" name="sku" maxlength="64"
                           value="{{ old('sku', $lubricant?->sku) }}"
                           class="form-control @error('sku') is-invalid @enderror">
                    @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input id="name" type="text" name="name" required maxlength="160"
                           value="{{ old('name', $lubricant?->name) }}"
                           class="form-control @error('name') is-invalid @enderror"
                           placeholder="e.g. Engine Oil 5W-30 5L">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="barcode" class="form-label">Barcode</label>
                    <input id="barcode" type="text" name="barcode" maxlength="64"
                           value="{{ old('barcode', $lubricant?->barcode) }}"
                           class="form-control @error('barcode') is-invalid @enderror">
                    @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="brand" class="form-label">Brand</label>
                    <input id="brand" type="text" name="brand" maxlength="120"
                           value="{{ old('brand', $lubricant?->brand) }}"
                           class="form-control @error('brand') is-invalid @enderror"
                           placeholder="e.g. Castrol, Shell, Mobil">
                    @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="manufacturer" class="form-label">Manufacturer</label>
                    <input id="manufacturer" type="text" name="manufacturer" maxlength="120"
                           value="{{ old('manufacturer', $lubricant?->manufacturer) }}"
                           class="form-control @error('manufacturer') is-invalid @enderror"
                           placeholder="e.g. BP, ExxonMobil, Chevron">
                    @error('manufacturer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="manufacturer_part_number" class="form-label">Manufacturer part #</label>
                    <input id="manufacturer_part_number" type="text" name="manufacturer_part_number" maxlength="64"
                           value="{{ old('manufacturer_part_number', $lubricant?->manufacturer_part_number) }}"
                           class="form-control @error('manufacturer_part_number') is-invalid @enderror">
                    @error('manufacturer_part_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="5000"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $lubricant?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Classification</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="lubricant_type" class="form-label">Type (base) <span class="text-danger">*</span></label>
                    <select id="lubricant_type" name="lubricant_type" required
                            class="form-select @error('lubricant_type') is-invalid @enderror">
                        @foreach($lubricantTypes as $t)
                            <option value="{{ $t->value }}" @selected(old('lubricant_type', $lubricant?->lubricant_type ?? 'mineral') === $t->value)>{{ $t->label() }}</option>
                        @endforeach
                    </select>
                    @error('lubricant_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="viscosity_grade" class="form-label">Viscosity grade</label>
                    <select id="viscosity_grade" name="viscosity_grade"
                            class="form-select @error('viscosity_grade') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($viscosities as $v)
                            <option value="{{ $v->value }}" @selected(old('viscosity_grade', $lubricant?->viscosity_grade) === $v->value)>{{ $v->label() }}</option>
                        @endforeach
                    </select>
                    @error('viscosity_grade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="application_type" class="form-label">Application</label>
                    <select id="application_type" name="application_type"
                            class="form-select @error('application_type') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($applications as $a)
                            <option value="{{ $a->value }}" @selected(old('application_type', $lubricant?->application_type) === $a->value)>{{ $a->label() }}</option>
                        @endforeach
                    </select>
                    @error('application_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select id="status" name="status" required
                            class="form-select @error('status') is-invalid @enderror">
                        @foreach($statuses as $st)
                            <option value="{{ $st->value }}" @selected(old('status', $lubricant?->status ?? 'active') === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Technical specifications</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="oem_specification" class="form-label">OEM specification</label>
                    <input id="oem_specification" type="text" name="oem_specification" maxlength="128"
                           value="{{ old('oem_specification', $lubricant?->oem_specification) }}"
                           class="form-control @error('oem_specification') is-invalid @enderror"
                           placeholder="e.g. MB-Approval 229.51">
                    <div class="form-text">Long approvals like "MB-Approval 229.51 / VW 504 00" are supported.</div>
                    @error('oem_specification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="acea_specification" class="form-label">ACEA</label>
                    <input id="acea_specification" type="text" name="acea_specification" maxlength="64"
                           value="{{ old('acea_specification', $lubricant?->acea_specification) }}"
                           class="form-control @error('acea_specification') is-invalid @enderror"
                           placeholder="e.g. A3/B4">
                    @error('acea_specification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="api_specification" class="form-label">API</label>
                    <input id="api_specification" type="text" name="api_specification" maxlength="64"
                           value="{{ old('api_specification', $lubricant?->api_specification) }}"
                           class="form-control @error('api_specification') is-invalid @enderror"
                           placeholder="e.g. SN, SP, CK-4">
                    @error('api_specification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="iso_grade" class="form-label">ISO grade</label>
                    <input id="iso_grade" type="text" name="iso_grade" maxlength="32"
                           value="{{ old('iso_grade', $lubricant?->iso_grade) }}"
                           class="form-control @error('iso_grade') is-invalid @enderror"
                           placeholder="e.g. VG 46">
                    <div class="form-text">Industrial / hydraulic viscosity grade.</div>
                    @error('iso_grade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="nlgi_grade" class="form-label">NLGI grade</label>
                    <input id="nlgi_grade" type="text" name="nlgi_grade" maxlength="32"
                           value="{{ old('nlgi_grade', $lubricant?->nlgi_grade) }}"
                           class="form-control @error('nlgi_grade') is-invalid @enderror"
                           placeholder="e.g. NLGI 2">
                    <div class="form-text">For greases.</div>
                    @error('nlgi_grade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Packaging</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="package_type" class="form-label">Package type <span class="text-danger">*</span></label>
                    <select id="package_type" name="package_type" required
                            class="form-select @error('package_type') is-invalid @enderror">
                        @foreach($packageTypes as $p)
                            <option value="{{ $p->value }}" @selected(old('package_type', $lubricant?->package_type ?? 'bottle') === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                    @error('package_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="package_size" class="form-label">Package size <span class="text-danger">*</span></label>
                    <input id="package_size" type="number" step="0.01" min="0" name="package_size" required
                           value="{{ old('package_size', $lubricant?->package_size ?? 0) }}"
                           class="form-control @error('package_size') is-invalid @enderror">
                    @error('package_size')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-3">
                    <label for="package_unit" class="form-label">Unit <span class="text-danger">*</span></label>
                    <select id="package_unit" name="package_unit" required
                            class="form-select @error('package_unit') is-invalid @enderror">
                        @php $unit = old('package_unit', $lubricant?->package_unit ?? 'L'); @endphp
                        @foreach(['L' => 'L (litres)', 'ml' => 'ml', 'kg' => 'kg', 'g' => 'g', 'gal' => 'gal (gallons)'] as $value => $label)
                            <option value="{{ $value }}" @selected($unit === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('package_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Inventory & pricing</h2>
            <div class="row g-3">
                <div class="col-6 col-md-4">
                    <label for="cost_price" class="form-label">Cost price <span class="text-danger">*</span></label>
                    <input id="cost_price" type="number" step="0.01" min="0" name="cost_price" required
                           value="{{ old('cost_price', $lubricant?->cost_price ?? 0) }}"
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
                                    @selected(old('bin_location_id', $lubricant?->bin_location_id) == $bin->id)>
                                {{ $bin->code }}{{ $bin->zone ? ' · '.$bin->zone : '' }}{{ $bin->aisle ? ' · '.$bin->aisle : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('bin_location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label for="reorder_threshold" class="form-label">Reorder threshold <span class="text-danger">*</span></label>
                    <input id="reorder_threshold" type="number" step="1" min="0" name="reorder_threshold" required
                           value="{{ old('reorder_threshold', $lubricant?->reorder_threshold ?? 0) }}"
                           class="form-control @error('reorder_threshold') is-invalid @enderror">
                    <div class="form-text">When on-hand drops to or below this value, the SKU is flagged low.</div>
                    @error('reorder_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6 col-md-4">
                    <label for="reorder_quantity" class="form-label">Reorder quantity <span class="text-danger">*</span></label>
                    <input id="reorder_quantity" type="number" step="1" min="0" name="reorder_quantity" required
                           value="{{ old('reorder_quantity', $lubricant?->reorder_quantity ?? 0) }}"
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
                                    @selected(old('supplier_id', $lubricant?->supplier_id) == $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Primary supplier for this lubricant.</div>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Notes</h2>
            <textarea id="notes" name="notes" rows="3"
                      class="form-control @error('notes') is-invalid @enderror"
                      placeholder="Internal notes about this lubricant…">{{ old('notes', $lubricant?->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card">
            <h2 class="h6 mb-3">Catalog state</h2>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       @checked(old('is_active', $lubricant?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="form-text">Inactive lubricants stay in the catalog but are hidden from operational dropdowns.</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.lubricants.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create lubricant' }}</button>
</div>