{{--
    Shared form partial for the assign / edit pages. Edit-mode receives
    the existing consumable; create-mode passes null.
--}}

@props([
    'consumable' => null,
    'equipment' => collect(),
    'preselectedEquipment' => null,
    'preselectedEquipmentId' => null,
    'parts' => collect(),
    'batteries' => collect(),
    'lubricants' => collect(),
    'units' => collect(),
    'bins' => collect(),
    'allowedResources' => [],
])

@php
    $isEdit = $consumable !== null;
    $resourceTypeValue = old('resource_type', $consumable?->resource_type ?? \App\Models\Part::class);
    $resourceIdValue = old('resource_id', $consumable?->resource_id);
    $equipmentId = old('equipment_id', $consumable?->equipment_id ?? $preselectedEquipmentId ?? $preselectedEquipment?->id);
    $assignedAt = old('assigned_at', $consumable?->assigned_at?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i'));
    $expectedAt = old('expected_replacement_at', $consumable?->expected_replacement_at?->format('Y-m-d'));
@endphp

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <h2 class="h6 mb-3">Equipment</h2>
            <div class="row g-3">
                <div class="col-12">
                    <label for="equipment_id" class="form-label">Equipment <span class="text-danger">*</span></label>
                    <select id="equipment_id" name="equipment_id" required
                            class="form-select @error('equipment_id') is-invalid @enderror"
                            @disabled($isEdit)>
                        <option value="">— Select equipment —</option>
                        @foreach($equipment as $eq)
                            <option value="{{ $eq->id }}" @selected((int) $equipmentId === (int) $eq->id)>
                                {{ $eq->name }}@if($eq->asset_number) · {{ $eq->asset_number }}@endif
                            </option>
                        @endforeach
                    </select>
                    @error('equipment_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Resource</h2>
            <x-admin.equipment-consumable.resource-type-picker
                :parts="$parts"
                :batteries="$batteries"
                :lubricants="$lubricants"
                :typeValue="$resourceTypeValue"
                :idValue="$resourceIdValue"
                :showTypeRadios="! $isEdit" />
        </div>

        <div class="admin-card mt-3">
            <h2 class="h6 mb-3">Quantity &amp; timing</h2>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                    <input type="number" step="0.001" min="0.001" id="quantity" name="quantity" required
                           value="{{ old('quantity', $consumable?->currentAssignment?->quantity ?? 1) }}"
                           class="form-control @error('quantity') is-invalid @enderror">
                    @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="unit_id" class="form-label">Unit</label>
                    <select id="unit_id" name="unit_id" class="form-select @error('unit_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach($units as $u)
                            <option value="{{ $u->id }}" @selected(old('unit_id', $consumable?->currentAssignment?->unit_id) == $u->id)>
                                {{ $u->name }}@if($u->short_code) ({{ $u->short_code }})@endif
                            </option>
                        @endforeach
                    </select>
                    @error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-4">
                    <label for="unit_cost" class="form-label">Unit cost</label>
                    <input type="number" step="0.0001" min="0" id="unit_cost" name="unit_cost"
                           value="{{ old('unit_cost', $consumable?->currentAssignment?->unit_cost) }}"
                           class="form-control @error('unit_cost') is-invalid @enderror">
                    @error('unit_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="bin_id" class="form-label">Bin</label>
                    <select id="bin_id" name="bin_id" class="form-select @error('bin_id') is-invalid @enderror">
                        <option value="">—</option>
                        @foreach($bins as $bin)
                            <option value="{{ $bin->id }}" @selected(old('bin_id', $consumable?->currentAssignment?->bin_id) == $bin->id)>
                                {{ $bin->code }}
                            </option>
                        @endforeach
                    </select>
                    @error('bin_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="assigned_at" class="form-label">Assigned at <span class="text-danger">*</span></label>
                    <input type="datetime-local" id="assigned_at" name="assigned_at" required
                           value="{{ $assignedAt }}"
                           class="form-control @error('assigned_at') is-invalid @enderror">
                    @error('assigned_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label for="expected_replacement_at" class="form-label">Expected replacement</label>
                    <input type="date" id="expected_replacement_at" name="expected_replacement_at"
                           value="{{ $expectedAt }}"
                           class="form-control @error('expected_replacement_at') is-invalid @enderror">
                    @error('expected_replacement_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="5000"
                              class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $consumable?->notes) }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="mt-3 d-flex justify-content-end gap-2">
            <a href="{{ $isEdit ? route('admin.equipment-consumables.show', $consumable) : route('admin.equipment-consumables.index') }}"
               class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check2 me-1"></i> {{ $isEdit ? 'Save changes' : 'Assign consumable' }}
            </button>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card">
            <h2 class="h6 mb-2">How it works</h2>
            <p class="text-muted small mb-2">
                Assigning a consumable registers the resource against the equipment.
                No stock moves until you record a <strong>consume</strong> event.
            </p>
            <p class="text-muted small mb-0">
                Lifecycle verbs: <strong>assign → install → consume → replace → remove</strong>.
                Every event is recorded as an append-only ledger row.
            </p>
        </div>
    </div>
</div>