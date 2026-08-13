@props([
    'variant' => 'success', // success | danger | warning | info
    'autohide' => true,
    'dismissible' => true,
])

@php
    $icon = match($variant) {
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-x-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
        default   => 'bi-info-circle-fill',
    };
@endphp

<div {{ $attributes->class(['alert', "alert-$variant"])->merge(['role' => 'alert']) }}
     @if($autohide) data-autohide @endif>
    <i class="bi {{ $icon }} me-2" aria-hidden="true"></i>
    {{ $slot }}
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>