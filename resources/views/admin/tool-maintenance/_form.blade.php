@php
    use App\Enums\ToolMaintenanceType;
@endphp

@props([
    'tool' => null,
    'record' => null,
    'users' => collect(),
])

@php
    $isEdit = $record !== null;
@endphp

<div class="admin-card">
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
            <select id="type" name="type" required
                    class="form-select @error('type') is-invalid @enderror">
                @foreach(ToolMaintenanceType::cases() as $t)
                    <option value="{{ $t->value }}" @selected(old('type', $record?->type?->value ?? 'preventive') === $t->value)>{{ $t->label() }}</option>
                @endforeach
            </select>
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="performed_by" class="form-label">Performed by</label>
            <select id="performed_by" name="performed_by"
                    class="form-select @error('performed_by') is-invalid @enderror">
                <option value="">— External / Vendor —</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(old('performed_by', $record?->performed_by) == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
            @error('performed_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="vendor" class="form-label">Vendor</label>
            <input id="vendor" type="text" name="vendor" maxlength="160"
                   value="{{ old('vendor', $record?->vendor) }}"
                   class="form-control @error('vendor') is-invalid @enderror">
            @error('vendor')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="cost" class="form-label">Cost</label>
            <input id="cost" type="number" step="0.01" min="0" name="cost"
                   value="{{ old('cost', $record?->cost) }}"
                   class="form-control @error('cost') is-invalid @enderror">
            @error('cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="performed_at" class="form-label">Performed at <span class="text-danger">*</span></label>
            <input id="performed_at" type="date" name="performed_at" required
                   value="{{ old('performed_at', $record?->performed_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                   class="form-control @error('performed_at') is-invalid @enderror">
            @error('performed_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label for="next_due_at" class="form-label">Next due</label>
            <input id="next_due_at" type="date" name="next_due_at"
                   value="{{ old('next_due_at', $record?->next_due_at?->format('Y-m-d')) }}"
                   class="form-control @error('next_due_at') is-invalid @enderror">
            <div class="form-text">Optional. When set, the tool appears on the maintenance-due list.</div>
            @error('next_due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
            <textarea id="description" name="description" rows="3" required maxlength="5000"
                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $record?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.tool-maintenance.index', $tool) }}" class="btn btn-outline-secondary">Cancel</a>
    <button class="btn btn-primary">{{ $isEdit ? 'Save changes' : 'Record maintenance' }}</button>
</div>
