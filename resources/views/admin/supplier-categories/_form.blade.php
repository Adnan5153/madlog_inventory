@props([
    'category' => null,
    'workshops' => collect(),
])

@php
    $isEdit = $category !== null;
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $category?->workshop_id ?? auth()->user()?->workshop_id
    );
@endphp

<div class="admin-card">
    <div class="row g-3">
        @if ($isGlobalAdmin)
            <div class="col-md-6">
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
                <div class="form-text">
                    The workshop this supplier category belongs to.
                </div>
                @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif

        <div class="col-12 col-md-6">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" type="text" name="name" required maxlength="120"
                value="{{ old('name', $category?->name) }}"
                class="form-control @error('name') is-invalid @enderror">
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="code" class="form-label">Code</label>
            <input id="code" type="text" name="code" maxlength="32"
                value="{{ old('code', $category?->code) }}"
                class="form-control @error('code') is-invalid @enderror">
            <div class="form-text">Auto-generated from the name when left blank.</div>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="3" maxlength="1000"
                class="form-control @error('description') is-invalid @enderror">{{ old('description', $category?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                    @checked(old('is_active', $category?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.supplier-categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create category' }}</button>
</div>
