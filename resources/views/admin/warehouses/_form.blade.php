@props(['warehouse' => null])

@php $isEdit = $warehouse !== null; @endphp

<div class="admin-card">
    <div class="row g-3">
        <div class="col-12 col-md-8">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" type="text" name="name" required maxlength="160"
                value="{{ old('name', $warehouse?->name) }}"
                class="form-control @error('name') is-invalid @enderror">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label for="slug" class="form-label">Slug</label>
            <input id="slug" type="text" name="slug" maxlength="160"
                value="{{ old('slug', $warehouse?->slug) }}"
                class="form-control @error('slug') is-invalid @enderror">
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" maxlength="160"
                value="{{ old('email', $warehouse?->email) }}"
                class="form-control @error('email') is-invalid @enderror">
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="phone" class="form-label">Phone</label>
            <input id="phone" type="text" name="phone" maxlength="64"
                value="{{ old('phone', $warehouse?->phone) }}"
                class="form-control @error('phone') is-invalid @enderror">
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="address" class="form-label">Address</label>
            <textarea id="address" name="address" rows="2" maxlength="500"
                class="form-control @error('address') is-invalid @enderror">{{ old('address', $warehouse?->address) }}</textarea>
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                    @checked(old('is_active', $warehouse?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.warehouses.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-warning">{{ $isEdit ? 'Save changes' : 'Create warehouse' }}</button>
</div>