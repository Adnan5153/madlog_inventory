@props([
    'label' => '',
    'value' => '',
    'meta' => null,
    'icon' => null,
    'variant' => 'default', // default | success | danger | info
])

@php
    $iconClass = match($variant) {
        'success' => 'is-success',
        'danger'  => 'is-danger',
        'info'    => 'is-info',
        default   => '',
    };
@endphp

<div class="admin-stat-card">
    @if($icon)
        <div class="stat-icon {{ $iconClass }}">
            <i class="bi {{ $icon }}" aria-hidden="true"></i>
        </div>
    @endif
    <div class="stat-label">{{ $label }}</div>
    <div class="stat-value">{{ $value }}</div>
    @if($meta)
        <div class="stat-meta">{{ $meta }}</div>
    @endif
</div>