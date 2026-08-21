@props([
    'title' => '',
    'value' => '',
    'meta' => null,
    'icon' => null,
    'variant' => 'neutral', // neutral | success | warning | danger | info | primary
    'href' => null,
])

@php
    $isLink = ! empty($href);
    $tag = $isLink ? 'a' : 'div';
    $attrs = $isLink
        ? ['href' => $href, 'class' => 'kpi-card kpi-card--'.$variant, 'aria-label' => $title]
        : ['class' => 'kpi-card kpi-card--'.$variant];
@endphp

<{{ $tag }} @foreach($attrs as $k => $v){{ $k }}="{{ $v }}" @endforeach>
    <span class="kpi-card__accent" aria-hidden="true"></span>
    @if($icon)
        <span class="kpi-card__icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
    @endif
    <span class="kpi-card__body">
        <span class="kpi-card__title">{{ $title }}</span>
        <span class="kpi-card__value">{{ $value }}</span>
        @if($meta)
            <span class="kpi-card__meta">{{ $meta }}</span>
        @endif
    </span>
</{{ $tag }}>