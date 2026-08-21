{{--
    Resource-type radio + dependent resource dropdown.

    Used on the assign form and the replace slide-over. The dropdown that
    lists concrete resources (parts / batteries / lubricants) is filtered
    server-side based on the selected type. On the assign form we read
    `old()` so a validation failure preserves the choice.
--}}

@props([
    'name' => 'resource',
    'typeName' => 'resource_type',
    'idName' => 'resource_id',
    'typeValue' => null,
    'idValue' => null,
    'parts' => collect(),
    'batteries' => collect(),
    'lubricants' => collect(),
    'showTypeRadios' => true,
])

@php
    $allowed = \App\Models\EquipmentConsumable::allowedResourceTypes();
    $partClass = \App\Models\Part::class;
    $batteryClass = \App\Models\Battery::class;
    $lubricantClass = \App\Models\Lubricant::class;

    // Resolve defaults that depend on the props above (so the
    // `old()` helper reads from the right request key).
    if ($typeValue === null) {
        $typeValue = old($typeName, $partClass);
    }
    if ($idValue === null) {
        $idValue = old($idName);
    }
@endphp

@if($showTypeRadios)
    <div class="resource-type-radios">
        <label class="form-label">{{ __('Resource type') }} <span class="text-danger">*</span></label>
        <div class="btn-group flex-wrap" role="group" aria-label="Resource type">
            @foreach([
                $partClass     => ['icon' => 'bi-nut',                'label' => 'Part'],
                $batteryClass  => ['icon' => 'bi-battery-charging',   'label' => 'Battery'],
                $lubricantClass=> ['icon' => 'bi-droplet-fill',       'label' => 'Lubricant'],
            ] as $class => $meta)
                <input type="radio" class="btn-check" name="{{ $typeName }}"
                       id="{{ $name }}_type_{{ $class }}" value="{{ $class }}"
                       @checked($typeValue === $class)>
                <label class="btn btn-outline-secondary" for="{{ $name }}_type_{{ $class }}">
                    <i class="bi {{ $meta['icon'] }} me-1" aria-hidden="true"></i>{{ $meta['label'] }}
                </label>
            @endforeach
        </div>
        @error($typeName)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
@endif

<div class="mt-3">
    <label for="{{ $idName }}" class="form-label">Resource <span class="text-danger">*</span></label>
    <div class="resource-pickers" data-resource-picker>
        <select id="{{ $idName }}_{{ $partClass }}" name="{{ $idName }}"
                class="form-select resource-picker resource-picker--{{ $partClass }}"
                @disabled($typeValue !== $partClass)>
            <option value="">— Select a part —</option>
            @foreach($parts as $p)
                <option value="{{ $p->id }}" @selected((string) $idValue === (string) $p->id && $typeValue === $partClass)>
                    {{ $p->name }}@if($p->sku) ({{ $p->sku }})@endif
                </option>
            @endforeach
        </select>

        <select id="{{ $idName }}_{{ $batteryClass }}" name="{{ $idName }}"
                class="form-select resource-picker resource-picker--{{ $batteryClass }}"
                @disabled($typeValue !== $batteryClass)>
            <option value="">— Select a battery —</option>
            @foreach($batteries as $b)
                <option value="{{ $b->id }}" @selected((string) $idValue === (string) $b->id && $typeValue === $batteryClass)>
                    {{ $b->name }}@if($b->battery_code) ({{ $b->battery_code }})@endif
                </option>
            @endforeach
        </select>

        <select id="{{ $idName }}_{{ $lubricantClass }}" name="{{ $idName }}"
                class="form-select resource-picker resource-picker--{{ $lubricantClass }}"
                @disabled($typeValue !== $lubricantClass)>
            <option value="">— Select a lubricant —</option>
            @foreach($lubricants as $l)
                <option value="{{ $l->id }}" @selected((string) $idValue === (string) $l->id && $typeValue === $lubricantClass)>
                    {{ $l->name }}@if($l->lubricant_code) ({{ $l->lubricant_code }})@endif
                </option>
            @endforeach
        </select>
    </div>
    @error($idName)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

@once
    @push('scripts')
        <script>
            (function () {
                document.querySelectorAll('[data-resource-picker]').forEach(function (root) {
                    var radios = document.querySelectorAll('input[type="radio"][name$="resource_type"]');
                    function sync() {
                        var current = null;
                        radios.forEach(function (r) { if (r.checked) current = r.value; });
                        root.querySelectorAll('.resource-picker').forEach(function (sel) {
                            var match = sel.classList.contains('resource-picker--' + current);
                            sel.disabled = !match;
                            if (!match) sel.value = '';
                        });
                    }
                    radios.forEach(function (r) { r.addEventListener('change', sync); });
                    sync();
                });
            })();
        </script>
    @endpush
@endonce
