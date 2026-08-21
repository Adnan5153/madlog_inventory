@props([
    'href',
    'icon' => 'bi-eye',
    'variant' => 'outline-secondary',
])

<a href="{{ $href }}" {{ $attributes->merge(['class' => "btn btn-sm btn-{$variant}"]) }}>
    <i class="bi {{ $icon }}"></i>
</a>