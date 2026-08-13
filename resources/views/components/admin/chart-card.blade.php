@props([
    'title',
    'icon' => 'bi-graph-up',
    'height' => 260,
    'hasData' => true,
    'emptyMessage' => 'No data to display yet.',
])

<div class="admin-card h-100">
    <h2 class="h6 mb-3">
        <i class="bi {{ $icon }} me-1"></i> {{ $title }}
    </h2>
    <div class="chart-wrap" style="position:relative; height: {{ (int) $height }}px;">
        {{ $slot }}
    </div>
    @unless($hasData)
        <p class="text-muted small mb-0 mt-2">{{ $emptyMessage }}</p>
    @endunless
</div>
