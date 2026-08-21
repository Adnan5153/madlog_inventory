{{--
    Shared form fields for the consume / replace / remove modals.
    Rendered inside a <form> by the parent _modals partial.
--}}

@props([
    'type' => 'consume',
    'units' => collect(),
    'bins' => collect(),
    'parts' => collect(),
    'batteries' => collect(),
    'lubricants' => collect(),
    'showResource' => false,
    'showReturn' => false,
])

<div class="row g-3">
    @if($showResource)
        <div class="col-12">
            <x-admin.equipment-consumable.resource-type-picker
                :parts="$parts"
                :batteries="$batteries"
                :lubricants="$lubricants"
                name="new_resource"
                typeName="new_resource_type"
                idName="new_resource_id" />
        </div>
    @endif

    <div class="col-12 col-md-6">
        <label for="{{ $type }}_quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
        <input type="number" step="0.001" min="0.001" id="{{ $type }}_quantity" name="quantity" required
               value="{{ old('quantity', 1) }}"
               class="form-control">
    </div>
    <div class="col-12 col-md-6">
        <label for="{{ $type }}_unit_id" class="form-label">Unit</label>
        <select id="{{ $type }}_unit_id" name="unit_id" class="form-select">
            <option value="">—</option>
            @foreach($units as $u)
                <option value="{{ $u->id }}" @selected(old('unit_id') == $u->id)>
                    {{ $u->name }}@if($u->short_code) ({{ $u->short_code }})@endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="col-12 col-md-6">
        <label for="{{ $type }}_bin_id" class="form-label">Bin</label>
        <select id="{{ $type }}_bin_id" name="bin_id" class="form-select">
            <option value="">—</option>
            @foreach($bins as $bin)
                <option value="{{ $bin->id }}" @selected(old('bin_id') == $bin->id)>
                    {{ $bin->code }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-12 col-md-6">
        <label for="{{ $type }}_unit_cost" class="form-label">Unit cost</label>
        <input type="number" step="0.0001" min="0" id="{{ $type }}_unit_cost" name="unit_cost"
               value="{{ old('unit_cost') }}" class="form-control">
    </div>

    <div class="col-12 col-md-6">
        <label for="{{ $type }}_performed_at" class="form-label">Performed at <span class="text-danger">*</span></label>
        <input type="datetime-local" id="{{ $type }}_performed_at" name="performed_at" required
               value="{{ old('performed_at', now()->format('Y-m-d\TH:i')) }}" class="form-control">
    </div>

    @if($showReturn)
        <div class="col-12 col-md-6 d-flex align-items-end">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="{{ $type }}_return_to_stock" name="return_to_stock" value="1">
                <label class="form-check-label" for="{{ $type }}_return_to_stock">
                    Return to stock
                </label>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <label for="{{ $type }}_return_quantity" class="form-label">Return quantity</label>
            <input type="number" step="0.001" min="0" id="{{ $type }}_return_quantity" name="return_quantity"
                   value="{{ old('return_quantity', 0) }}" class="form-control">
        </div>
    @endif

    <div class="col-12">
        <label for="{{ $type }}_notes" class="form-label">Notes</label>
        <textarea id="{{ $type }}_notes" name="notes" rows="2" maxlength="5000" class="form-control">{{ old('notes') }}</textarea>
    </div>
</div>