@props([
    'equipment' => null,
    'departments' => collect(),
    'bins' => collect(),
    'workshops' => collect(),
])

@php
    $isGlobalAdmin = auth()->user()?->isGlobalAdmin() ?? false;
    $selectedWorkshopId = old(
        'workshop_id',
        $equipment?->workshop_id ?? auth()->user()?->workshop_id
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
                <div class="form-text">The workshop this equipment belongs to.</div>
                @error('workshop_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif

        <div class="col-md-8">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input id="name" name="name" type="text" required maxlength="160"
                   value="{{ old('name', $equipment?->name) }}"
                   class="form-control @error('name') is-invalid @enderror">
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label for="asset_number" class="form-label">Asset #</label>
            <input id="asset_number" name="asset_number" type="text" maxlength="64"
                   value="{{ old('asset_number', $equipment?->asset_number) }}"
                   class="form-control @error('asset_number') is-invalid @enderror">
            @error('asset_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-4">
            <label for="equipment_type" class="form-label">Type</label>
            <input id="equipment_type" name="equipment_type" type="text" maxlength="64"
                   value="{{ old('equipment_type', $equipment?->equipment_type) }}"
                   class="form-control @error('equipment_type') is-invalid @enderror">
        </div>
        <div class="col-md-4">
            <label for="manufacturer" class="form-label">Manufacturer</label>
            <input id="manufacturer" name="manufacturer" type="text" maxlength="120"
                   value="{{ old('manufacturer', $equipment?->manufacturer) }}"
                   class="form-control @error('manufacturer') is-invalid @enderror">
        </div>
        <div class="col-md-4">
            <label for="model" class="form-label">Model</label>
            <input id="model" name="model" type="text" maxlength="120"
                   value="{{ old('model', $equipment?->model) }}"
                   class="form-control @error('model') is-invalid @enderror">
        </div>
        <div class="col-md-6">
            <label for="serial_number" class="form-label">Serial #</label>
            <input id="serial_number" name="serial_number" type="text" maxlength="120"
                   value="{{ old('serial_number', $equipment?->serial_number) }}"
                   class="form-control @error('serial_number') is-invalid @enderror">
        </div>
        <div class="col-md-3">
            <label for="purchase_date" class="form-label">Purchase date</label>
            <input id="purchase_date" name="purchase_date" type="date"
                   value="{{ old('purchase_date', optional($equipment?->purchase_date)->format('Y-m-d')) }}"
                   class="form-control @error('purchase_date') is-invalid @enderror">
        </div>
        <div class="col-md-3">
            <label for="warranty_expires_at" class="form-label">Warranty expires</label>
            <input id="warranty_expires_at" name="warranty_expires_at" type="date"
                   value="{{ old('warranty_expires_at', optional($equipment?->warranty_expires_at)->format('Y-m-d')) }}"
                   class="form-control @error('warranty_expires_at') is-invalid @enderror">
        </div>
        <div class="col-md-6">
            <label for="department_id" class="form-label">Department</label>
            <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                <option value="">— None —</option>
                @foreach($departments as $d)
                    <option value="{{ $d->id }}" @selected((string) old('department_id', $equipment?->department_id) === (string) $d->id)>
                        {{ $d->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="bin_location_id" class="form-label">Storage bin</label>
            <select id="bin_location_id" name="bin_location_id" class="form-select @error('bin_location_id') is-invalid @enderror">
                <option value="">— None —</option>
                @foreach($bins as $b)
                    <option value="{{ $b->id }}" @selected((string) old('bin_location_id', $equipment?->bin_location_id) === (string) $b->id)>
                        {{ $b->code }}
                    </option>
                @endforeach
            </select>
            @error('bin_location_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                @foreach(['active','maintenance','retired','disposed'] as $opt)
                    <option value="{{ $opt }}" @selected(old('status', $equipment?->status ?? 'active') === $opt)>{{ ucfirst($opt) }}</option>
                @endforeach
            </select>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6 d-flex align-items-end">
            <div class="form-check">
                <input id="is_active" name="is_active" value="1" type="checkbox"
                       class="form-check-input"
                       @checked(old('is_active', $equipment?->is_active ?? true))>
                <label for="is_active" class="form-check-label">Active</label>
            </div>
        </div>
        <div class="col-12">
            <label for="notes" class="form-label">Notes</label>
            <textarea id="notes" name="notes" rows="3" maxlength="1000"
                      class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $equipment?->notes) }}</textarea>
            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>
