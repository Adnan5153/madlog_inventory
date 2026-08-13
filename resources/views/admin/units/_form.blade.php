@props(['unit' => null])

<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" name="name" type="text" required maxlength="120"
                   value="{{ old('name', $unit?->name) }}"
                   class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label for="short_code" class="form-label">Short code <span class="text-danger">*</span></label>
            <input id="short_code" name="short_code" type="text" required maxlength="8"
                   value="{{ old('short_code', $unit?->short_code) }}"
                   class="form-control @error('short_code') is-invalid @enderror">
            @error('short_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-3">
            <label for="decimal_precision" class="form-label">Decimals</label>
            <input id="decimal_precision" name="decimal_precision" type="number" min="0" max="6" required
                   value="{{ old('decimal_precision', $unit?->decimal_precision ?? 0) }}"
                   class="form-control @error('decimal_precision') is-invalid @enderror">
            @error('decimal_precision') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="2" maxlength="500"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $unit?->description) }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <div class="form-check">
                <input id="is_active" name="is_active" value="1" type="checkbox"
                       class="form-check-input"
                       @checked(old('is_active', $unit?->is_active ?? true))>
                <label for="is_active" class="form-check-label">Active</label>
            </div>
        </div>
    </div>
</div>