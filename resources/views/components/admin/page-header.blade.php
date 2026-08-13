@props([
    'title' => null,
    'subtitle' => null,
])

<div class="admin-page-header">
    <div>
        @if($title)
            <h1>{{ $title }}</h1>
        @endif
        @if($subtitle)
            <p class="subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="d-flex gap-2 flex-wrap">
        {{ $actions ?? '' }}
    </div>
</div>