@props([
    'department' => null,
    'managers' => collect(),
    'workshops' => collect(),
])

@php
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $department?->workshop_id ?? auth()->user()?->workshop_id
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
                <div class="form-text">The workshop this department belongs to.</div>
                @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif

        <div class="col-md-6">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" name="name" type="text" required maxlength="120"
                   value="{{ old('name', $department?->name) }}"
                   class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="code" class="form-label">Code <span class="text-danger">*</span></label>
            <input id="code" name="code" type="text" required maxlength="32"
                   value="{{ old('code', $department?->code) }}"
                   class="form-control @error('code') is-invalid @enderror">
            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="2" maxlength="500"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $department?->description) }}</textarea>
            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="manager_id" class="form-label">Manager</label>
            <select id="manager_id" name="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
                <option value="">— None —</option>
                @foreach($managers as $manager)
                    <option value="{{ $manager->id }}" @selected((string) old('manager_id', $department?->manager_id) === (string) $manager->id)>
                        {{ $manager->name }}
                    </option>
                @endforeach
            </select>
            @error('manager_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
                <input id="is_active" name="is_active" value="1" type="checkbox"
                       class="form-check-input"
                       @checked(old('is_active', $department?->is_active ?? true))>
                <label for="is_active" class="form-check-label">Active</label>
            </div>
        </div>
    </div>
</div>
