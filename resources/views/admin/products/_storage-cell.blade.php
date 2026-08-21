@php
    // Display precedence: free-text location wins; otherwise fall back to
    // the bin's full path; otherwise em-dash.
    $part = $part ?? $product ?? $p ?? null;
    $compact = $compact ?? false;
@endphp

@if($part?->location)
    {{ $part->location }}
@elseif($part?->binLocation)
    {{ $part->binLocation->code }}
    @unless($compact)
        @if($part->binLocation->zone) · {{ $part->binLocation->zone }} @endif
        @if($part->binLocation->aisle) · {{ $part->binLocation->aisle }} @endif
        @if($part->binLocation->shelf) · {{ $part->binLocation->shelf }} @endif
    @endunless
@elseif($compact)
    <span class="text-muted">—</span>
@else
    —
@endif
