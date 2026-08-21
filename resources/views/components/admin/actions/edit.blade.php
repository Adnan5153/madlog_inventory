@props([
    'href',
    'icon' => 'bi-pencil',
    'label' => null,
    'variant' => 'outline-secondary',
])

<x-admin.actions.view
    :href="$href"
    :icon="$icon"
    :label="$label"
    :variant="$variant"
    {{ $attributes }} />