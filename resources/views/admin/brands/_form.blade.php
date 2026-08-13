@props(['brand' => null])

<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" name="name" type="text" required maxlength="120"
                   value="{{ old('name', $brand?->name) }}"
                   class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="slug" class="form-label">Slug</label>
            <input id="slug" name="slug" type="text" maxlength="120"
                   value="{{ old('slug', $brand?->slug) }}"
                   placeholder="(auto-generated from name)"
                   class="form-control @error('slug') is-invalid @enderror">
            @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>