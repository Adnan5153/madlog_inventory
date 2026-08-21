@props([
    'category' => null,
    'workshops' => collect(),
])

@php
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    // Pre-select the existing workshop for global admins on edit, or
    // the user's own workshop otherwise. The form request will force
    // the value server-side even if the user is not a global admin.
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
                    The workshop this category belongs to. Each workshop keeps its own catalog.
                </div>
                @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif

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
