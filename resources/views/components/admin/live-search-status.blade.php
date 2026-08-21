{{--
    Standard status indicator + count line used by every live-search list page.

    Markup expected by resources/js/app.js (section 9b):
      - `[data-live-search-status]` — toggled visible/hidden by the JS.
      - `[data-live-search-status-label]` — the JS swaps its text.
      - `[data-live-search-count]`     — the JS sets its textContent.

    `singular` is the noun used in the count, e.g. "user", "product".
--}}

@props(['singular' => 'item'])

<div class="d-flex align-items-center justify-content-between gap-3 small text-muted mb-2">
    <div class="d-flex align-items-center gap-2" data-live-search-status hidden>
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
        <span data-live-search-status-label>Searching…</span>
    </div>
    <div class="ms-auto" data-live-search-count>
        {{-- Server renders an initial count; JS replaces this on every fetch. --}}
        {{ $slot ?? '' }}
    </div>
</div>