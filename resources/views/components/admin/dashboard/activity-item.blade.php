@props([
    'event' => '',
    'description' => '',
    'actor' => 'System',
    'subject' => null,
    'subjectHref' => null,
    'timestamp' => null,    // CarbonImmutable instance
    'icon' => 'bi-activity',
    'variant' => 'neutral', // neutral | success | warning | danger | info | primary
])

@php
    $eventLabel = \Illuminate\Support\Str::headline($event);
    $absolute = $timestamp instanceof \Carbon\CarbonInterface ? $timestamp->format('Y-m-d H:i') : '';
@endphp

<div class="activity-item">
    <span class="activity-item__icon activity-item__icon--{{ $variant }}" aria-hidden="true">
        <i class="bi {{ $icon }}"></i>
    </span>
    <span class="activity-item__body">
        <span class="activity-item__event">{{ $eventLabel }}</span>
        <span class="activity-item__description">
            {{ $description }}
            @if($subject)
                @if($subjectHref)
                    <a href="{{ $subjectHref }}" class="activity-item__subject">{{ $subject }}</a>
                @else
                    <span class="activity-item__subject">{{ $subject }}</span>
                @endif
            @endif
        </span>
        <span class="activity-item__actor">{{ $actor }}</span>
    </span>
    @if($timestamp instanceof \Carbon\CarbonInterface)
        <span class="activity-item__meta" title="{{ $absolute }}">
            <time datetime="{{ $timestamp->toIso8601String() }}">{{ $timestamp->diffForHumans() }}</time>
        </span>
    @endif
</div>