@props([
    'segments' => [],   // [['label','count','percent','color','href']]
    'total' => 0,
])

@php
    // Map segment 'color' to a CSS class; fall back to neutral.
    $colorClass = static fn (string $color): string => in_array($color, ['success','warning','danger','info','primary','secondary','neutral'], true)
        ? 'is-'.$color
        : 'is-neutral';
@endphp

@if($total === 0)
    <x-admin.empty-state icon="bi-bar-chart" title="No tools yet">
        Once tools are registered their status distribution will appear here.
    </x-admin.empty-state>
@else
    <div class="status-segmented-bar" role="img" aria-label="Tool status distribution">
        @foreach($segments as $seg)
            @php
                $w = (float) ($seg['percent'] ?? 0);
                // Floor tiny segments so they remain visible (>= 1.5%) when present.
                $flex = $w > 0 && $w < 1.5 ? 1.5 : $w;
            @endphp
            @if($flex > 0)
                <span class="status-segmented-bar__seg status-segmented-bar__seg--{{ $colorClass($seg['color'] ?? 'neutral') }}"
                      style="flex-basis: {{ $flex }}%;"
                      title="{{ $seg['label'] }}: {{ $seg['count'] }} ({{ $w }}%)"></span>
            @endif
        @endforeach
    </div>

    <dl class="status-segmented-bar__legend list-unstyled mb-0">
        @foreach($segments as $seg)
            <div class="status-segmented-bar__legend-row">
                <span class="status-segmented-bar__legend-dot status-segmented-bar__seg--{{ $colorClass($seg['color'] ?? 'neutral') }}" aria-hidden="true"></span>
                @if(! empty($seg['href']))
                    <a class="status-segmented-bar__legend-label" href="{{ $seg['href'] }}">{{ $seg['label'] }}</a>
                @else
                    <span class="status-segmented-bar__legend-label">{{ $seg['label'] }}</span>
                @endif
                <span class="status-segmented-bar__legend-count">{{ $seg['count'] }}</span>
                <span class="status-segmented-bar__legend-percent">{{ $seg['percent'] ?? 0 }}%</span>
            </div>
        @endforeach
    </dl>
@endif