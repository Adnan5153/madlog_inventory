@props([
    'variant' => 'default', // default | success | warning | danger | info
    'icon' => null,
    'on' => false,
])

@php
    // When the caller passes `:on="..."` without a variant we infer one.
    if ($variant === 'default' && $on !== false && $on !== null) {
        $variant = $on ? 'success' : 'danger';
        $label = $on ? 'Active' : 'Inactive';
    } else {
        $label = null;
    }
@endphp

<span class="admin-status-badge is-{{ $variant }}">
    @if($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
    @if($label)
        {{ $label }}
    @else
        {{ $slot }}
    @endif
</span>