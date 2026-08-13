@props([
    'icon' => 'bi-inbox',
    'title' => 'Nothing here yet',
])

<div class="admin-empty-state">
    <i class="bi {{ $icon }}" aria-hidden="true"></i>
    <h2 class="h5 mb-2">{{ $title }}</h2>
    <div>{{ $slot }}</div>
</div>