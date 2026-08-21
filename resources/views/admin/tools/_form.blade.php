@php
    use App\Enums\ToolCondition;
    use App\Enums\ToolStatus;
@endphp

@props([
    'tool' => null,
    'workshops' => collect(),
    'pickedWorkshopId' => null,
    'binLocations' => collect(),
    'suppliers' => collect(),
    'categories' => collect(),
    'users' => collect(),
    'statuses' => [],
    'conditions' => [],
])

@php
    $isEdit = $tool !== null;
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $tool?->workshop_id ?? $pickedWorkshopId ?? auth()->user()?->workshop_id
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
                                onchange="if (this.value) { window.location.assign('{{ route('admin.tools.create') }}?workshop_id=' + encodeURIComponent(this.value)); }">
                            <option value="">— Select a workshop —</option>
                            @foreach ($workshops as $workshop)
                                <option value="{{ $workshop->id }}"
                                        @selected((int) $selectedWorkshopId === (int) $workshop->id)>
                                    {{ $workshop->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        @endif

        <div class="admin-card {{ $isGlobalAdmin ? 'mt-3' : '' }}">
            <h2 class="h6 mb-3">Identification</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="tool_code" class="form-label">Tool code <span class="text-danger">*</span></label>
                    <input id="tool_code" type="text" name="tool_code" required maxlength="64"
                           value="{{ old('tool_code', $tool?->tool_code) }}"
                           class="form-control @error('tool_code') is-invalid @enderror"
                           placeholder="e.g. TL-001">
                    @error('tool_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input id="name" type="text" name="name" required maxlength="160"
                           value="{{ old('name', $tool?->name) }}"
                           class="form-control @error('name') is-invalid @enderror"
                           placeholder="e.g. Torque Wrench 1/2&quot;">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="category_id" class="form-label">Category</label>
                    <select id="category_id" name="category_id"
                            class="form-select @error('category_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $tool?->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="brand" class="form-label">Brand</label>
                    <input id="brand" type="text" name="brand" maxlength="120"
                           value="{{ old('brand', $tool?->brand) }}"
                           class="form-control @error('brand') is-invalid @enderror"
                           placeholder="e.g. Snap-on, Bosch">
                    @error('brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="model" class="form-label">Model</label>
                    <input id="model" type="text" name="model" maxlength="120"
                           value="{{ old('model', $tool?->model) }}"
                           class="form-control @error('model') is-invalid @enderror"
                           placeholder="e.g. 1HM2">
                    @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="serial_number" class="form-label">Serial number</label>
                    <input id="serial_number" type="text" name="serial_number" maxlength="64"
                           value="{{ old('serial_number', $tool?->serial_number) }}"
                           class="form-control @error('serial_number') is-invalid @enderror">
                    @error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="barcode" class="form-label">Barcode</label>
                    <input id="barcode" type="text" name="barcode" maxlength="64"
                           value="{{ old('barcode', $tool?->barcode) }}"
                           class="form-control @error('barcode') is-invalid @enderror">
                    @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="qr_code" class="form-label">QR code</label>
                    <input id="qr_code" type="text" name="qr_code" maxlength="64"
                           value="{{ old('qr_code', $tool?->qr_code) }}"
                           class="form-control @error('qr_code') is-invalid @enderror">
                    @error('qr_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="5000"
                              class="form-control @error('description') is-invalid @enderror">{{ old('description', $tool?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Location & supplier</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="bin_id" class="form-label">Bin location</label>
                    <select id="bin_id" name="bin_id"
                            class="form-select @error('bin_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($binLocations as $bin)
                            <option value="{{ $bin->id }}" @selected(old('bin_id', $tool?->bin_id) == $bin->id)>
                                {{ $bin->code }}{{ $bin->zone ? ' · '.$bin->zone : '' }}{{ $bin->aisle ? ' · '.$bin->aisle : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('bin_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select id="supplier_id" name="supplier_id"
                            class="form-select @error('supplier_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $tool?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Acquisition</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="purchase_date" class="form-label">Purchase date</label>
                    <input id="purchase_date" type="date" name="purchase_date"
                           value="{{ old('purchase_date', $tool?->purchase_date?->format('Y-m-d')) }}"
                           class="form-control @error('purchase_date') is-invalid @enderror">
                    @error('purchase_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="purchase_price" class="form-label">Purchase price</label>
                    <input id="purchase_price" type="number" step="0.01" min="0" name="purchase_price"
                           value="{{ old('purchase_price', $tool?->purchase_price) }}"
                           class="form-control @error('purchase_price') is-invalid @enderror">
                    @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="warranty_expiry" class="form-label">Warranty expiry</label>
                    <input id="warranty_expiry" type="date" name="warranty_expiry"
                           value="{{ old('warranty_expiry', $tool?->warranty_expiry?->format('Y-m-d')) }}"
                           class="form-control @error('warranty_expiry') is-invalid @enderror">
                    @error('warranty_expiry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Status & condition</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                    <select id="status" name="status" required
                            class="form-select @error('status') is-invalid @enderror">
                        @foreach($statuses as $st)
                            <option value="{{ $st->value }}" @selected(old('status', $tool?->status ?? 'available') === $st->value)>{{ $st->label() }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="condition" class="form-label">Condition <span class="text-danger">*</span></label>
                    <select id="condition" name="condition" required
                            class="form-select @error('condition') is-invalid @enderror">
                        @foreach($conditions as $c)
                            <option value="{{ $c->value }}" @selected(old('condition', $tool?->condition ?? 'good') === $c->value)>{{ $c->label() }}</option>
                        @endforeach
                    </select>
                    @error('condition')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="current_holder_user_id" class="form-label">Current holder</label>
                    <select id="current_holder_user_id" name="current_holder_user_id"
                            class="form-select @error('current_holder_user_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" @selected(old('current_holder_user_id', $tool?->current_holder_user_id) == $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Normally set via the checkout/checkin flow. Use this to manually reassign.</div>
                    @error('current_holder_user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Notes</h2>
            <textarea id="notes" name="notes" rows="3"
                      class="form-control @error('notes') is-invalid @enderror"
                      placeholder="Internal notes about this tool…">{{ old('notes', $tool?->notes) }}</textarea>
            @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card">
            <h2 class="h6 mb-3">Catalog state</h2>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       @checked(old('is_active', $tool?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="form-text">Inactive tools stay in the catalog but are hidden from operational dropdowns.</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create tool' }}</button>
</div>
