@props([
    'bin' => null,
    'workshops' => collect(),
])

@php
    $isEdit = $bin !== null;
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $bin?->workshop_id ?? auth()->user()?->workshop_id
    );
@endphp

<div class="admin-card">
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
                <div class="form-text">The workshop this bin belongs to.</div>
                @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif

        <div class="col-12 col-md-6">
            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
            <input id="code" type="text" name="code" required maxlength="32"
                value="{{ old('code', $bin?->code) }}"
                class="form-control @error('code') is-invalid @enderror">
            <div class="form-text">Workshop-local, unique. e.g. <code>A-12</code>, <code>B-RACK-3</code>.</div>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="zone" class="form-label">Zone</label>
            <input id="zone" type="text" name="zone" maxlength="64"
                value="{{ old('zone', $bin?->zone) }}"
                class="form-control @error('zone') is-invalid @enderror">
            <div class="form-text">Optional. Group bins by area (e.g. Brakes, Engine).</div>
            @error('zone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="aisle" class="form-label">Aisle</label>
            <input id="aisle" type="text" name="aisle" maxlength="32"
                value="{{ old('aisle', $bin?->aisle) }}"
                class="form-control @error('aisle') is-invalid @enderror">
            @error('aisle')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="shelf" class="form-label">Shelf</label>
            <input id="shelf" type="text" name="shelf" maxlength="32"
                value="{{ old('shelf', $bin?->shelf) }}"
                class="form-control @error('shelf') is-invalid @enderror">
            @error('shelf')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="2" maxlength="1000"
                class="form-control @error('description') is-invalid @enderror">{{ old('description', $bin?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                    @checked(old('is_active', $bin?->is_active ?? true))>
                <label class="form-check-label" for="is_active">Active</label>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.bin-locations.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Create bin' }}</button>
</div>
