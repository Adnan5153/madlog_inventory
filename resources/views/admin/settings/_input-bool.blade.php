@props(['name', 'value' => null])

<div class="form-check form-switch">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1"
           id="{{ $name }}"
           class="form-check-input"
           @checked((bool) old($name, $value))>
    <label for="{{ $name }}" class="form-check-label">
        <span class="text-muted small">Currently:</span>
        <strong>{{ $value ? 'Enabled' : 'Disabled' }}</strong>
    </label>
</div>