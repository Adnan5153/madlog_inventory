@props([
    'supplier' => null,
    'workshops' => collect(),
])

@php
    $isEdit = $supplier !== null;
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $supplier?->workshop_id ?? auth()->user()?->workshop_id
    );
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <h2 class="h6 mb-3">Identification</h2>
            <div class="row g-3">
                @if ($isGlobalAdmin)
                    <div class="col-12 col-md-6">
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
                        <div class="form-text">The workshop this supplier belongs to.</div>
                        @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                @endif

                <div class="col-12 col-md-6">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input id="name" type="text" name="name" required maxlength="160"
                        value="{{ old('name', $supplier?->name) }}"
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="contact_name" class="form-label">Primary contact</label>
                    <input id="contact_name" type="text" name="contact_name" maxlength="160"
                        value="{{ old('contact_name', $supplier?->contact_name) }}"
                        class="form-control @error('contact_name') is-invalid @enderror">
                    @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="email" class="form-label">Email</label>
                    <input id="email" type="email" name="email" maxlength="160"
                        value="{{ old('email', $supplier?->email) }}"
                        class="form-control @error('email') is-invalid @enderror">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input id="phone" type="text" name="phone" maxlength="64"
                        value="{{ old('phone', $supplier?->phone) }}"
                        class="form-control @error('phone') is-invalid @enderror">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="tax_id" class="form-label">Tax ID</label>
                    <input id="tax_id" type="text" name="tax_id" maxlength="64"
                        value="{{ old('tax_id', $supplier?->tax_id) }}"
                        class="form-control @error('tax_id') is-invalid @enderror">
                    @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="supplier_category_id" class="form-label">Category</label>
                    <select id="supplier_category_id" name="supplier_category_id" class="form-select @error('supplier_category_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('supplier_category_id', $supplier?->supplier_category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="address" class="form-label">Address</label>
                    <textarea id="address" name="address" rows="2" maxlength="1000"
                        class="form-control @error('address') is-invalid @enderror">{{ old('address', $supplier?->address) }}</textarea>
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="2000"
                        class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $supplier?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card">
            <h2 class="h6 mb-3">Status</h2>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                    @checked(old('is_active', $supplier?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
            <div class="form-text">Inactive suppliers stay in the catalog but are hidden from operational dropdowns.</div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.suppliers.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create supplier' }}</button>
</div>
