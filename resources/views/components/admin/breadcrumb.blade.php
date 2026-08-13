@props([
    'items' => [], // [['label' => 'X', 'url' => '/admin/x'], ['label' => 'Y']]
])

<nav class="admin-breadcrumb" aria-label="breadcrumb">
    <a href="{{ route('admin.dashboard') }}"><i class="bi bi-house"></i></a>
    @foreach($items as $i => $item)
        <i class="bi bi-chevron-right"></i>
        @if($loop->last || empty($item['url']))
            <span>{{ $item['label'] }}</span>
        @else
            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
        @endif
    @endforeach
</nav>