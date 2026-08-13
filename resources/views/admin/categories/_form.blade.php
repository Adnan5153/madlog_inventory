@props([
    'category' => null,
])

<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" name="name" type="text" required maxlength="120"
                   value="{{ old('name', $category?->name) }}"
                   class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="slug" class="form-label">Slug</label>
            <input id="slug" name="slug" type="text" maxlength="120"
                   value="{{ old('slug', $category?->slug) }}"
                   placeholder="(auto-generated from name)"
                   class="form-control @error('slug') is-invalid @enderror">
            <div class="form-text">Leave blank to auto-generate from the name.</div>
            @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="500"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $category?->description) }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>