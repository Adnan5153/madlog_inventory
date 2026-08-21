@props([
    'label' => '',
    'count' => 0,
    'href' => '#',
    'priority' => 'medium', // critical | high | medium
    'description' => null,
])

@php
    $priorityLabel = match($priority) {
        'critical' => 'Critical',
        'high'     => 'High',
        default    => 'Medium',
    };
@endphp

<a class="attention-item" href="{{ $href }}" aria-label="{{ $label }}: {{ $count }}">
    <span class="attention-item__dot attention-item__dot--{{ $priority }}" aria-hidden="true"></span>
    <span class="attention-item__body">
        <span class="attention-item__label">
            {{ $label }}
            <span class="attention-item__priority attention-item__priority--{{ $priority }}">{{ $priorityLabel }}</span>
        </span>
        @if($description)
            <span class="attention-item__description">{{ $description }}</span>
        @endif
    </span>
    <span class="attention-item__count" aria-hidden="true">{{ $count }}</span>
    <i class="bi bi-chevron-right attention-item__chevron" aria-hidden="true"></i>
</a>