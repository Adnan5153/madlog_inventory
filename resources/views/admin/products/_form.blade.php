@props(['product' => null])

@php
    $isEdit = $product !== null;
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <h2 class="h6 mb-3">Identification</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input id="name" type="text" name="name" required maxlength="160"
                        value="{{ old('name', $product?->name) }}"
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="sku" class="form-label">SKU</label>
                    <input id="sku" type="text" name="sku" maxlength="64"
                        value="{{ old('sku', $product?->sku) }}"
                        class="form-control @error('sku') is-invalid @enderror">
                    @error('sku')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="oem_part_number" class="form-label">OEM part number</label>
                    <input id="oem_part_number" type="text" name="oem_part_number" maxlength="64"
                        value="{{ old('oem_part_number', $product?->oem_part_number) }}"
                        class="form-control @error('oem_part_number') is-invalid @enderror">
                    @error('oem_part_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="barcode" class="form-label">Barcode</label>
                    <input id="barcode" type="text" name="barcode" maxlength="64"
                        value="{{ old('barcode', $product?->barcode) }}"
                        class="form-control @error('barcode') is-invalid @enderror">
                    @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" rows="3" maxlength="5000"
                        class="form-control @error('description') is-invalid @enderror">{{ old('description', $product?->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Pricing</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="cost_price" class="form-label">Cost price <span class="text-danger">*</span></label>
                    <input id="cost_price" type="number" step="0.01" min="0" name="cost_price" required
                        value="{{ old('cost_price', $product?->cost_price ?? 0) }}"
                        class="form-control @error('cost_price') is-invalid @enderror">
                    @error('cost_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="sale_price" class="form-label">Sale price <span class="text-danger">*</span></label>
                    <input id="sale_price" type="number" step="0.01" min="0" name="sale_price" required
                        value="{{ old('sale_price', $product?->sale_price ?? 0) }}"
                        class="form-control @error('sale_price') is-invalid @enderror">
                    @error('sale_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Reorder policy</h2>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label for="reorder_threshold" class="form-label">Reorder threshold <span class="text-danger">*</span></label>
                    <input id="reorder_threshold" type="number" step="1" min="0" name="reorder_threshold" required
                        value="{{ old('reorder_threshold', $product?->reorder_threshold ?? 0) }}"
                        class="form-control @error('reorder_threshold') is-invalid @enderror">
                    <div class="form-text">When aggregated on-hand quantity drops to or below this value, the part is flagged on the Low Stock report.</div>
                    @error('reorder_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="reorder_quantity" class="form-label">Reorder quantity <span class="text-danger">*</span></label>
                    <input id="reorder_quantity" type="number" step="1" min="0" name="reorder_quantity" required
                        value="{{ old('reorder_quantity', $product?->reorder_quantity ?? 0) }}"
                        class="form-control @error('reorder_quantity') is-invalid @enderror">
                    @error('reorder_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card">
            <h2 class="h6 mb-3">Classification</h2>
            <div class="mb-3">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror">
                    <option value="">— None —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id', $product?->category_id) == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="brand_id" class="form-label">Brand</label>
                <select id="brand_id" name="brand_id" class="form-select @error('brand_id') is-invalid @enderror">
                    <option value="">— None —</option>
                    @foreach($brands as $b)
                        <option value="{{ $b->id }}" @selected(old('brand_id', $product?->brand_id) == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                @error('brand_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="unit_id" class="form-label">Unit of measure</label>
                <select id="unit_id" name="unit_id" class="form-select @error('unit_id') is-invalid @enderror">
                    <option value="">— None —</option>
                    @foreach($units as $u)
                        <option value="{{ $u->id }}" @selected(old('unit_id', $product?->unit_id) == $u->id)>{{ $u->name }} ({{ $u->short_code }})</option>
                    @endforeach
                </select>
                @error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Status</h2>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                    @checked(old('is_active', $product?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="form-text">Inactive products stay in the catalog but are hidden from operational dropdowns.</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-warning">{{ $isEdit ? 'Save changes' : 'Create product' }}</button>
</div>