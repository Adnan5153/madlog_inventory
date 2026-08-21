@props([
    'action',
    'icon' => 'bi-trash',
    'confirm' => 'Delete this record?',
    'variant' => 'outline-danger',
])

<form method="POST" action="{{ $action }}"
      {{ $attributes->merge(['class' => 'd-inline']) }}
      data-confirm-form
      data-confirm="{{ $confirm }}">
    @csrf
    @method('DELETE')
    <button class="btn btn-sm btn-{{ $variant }}">
        <i class="bi {{ $icon }}"></i>
    </button>
</form>