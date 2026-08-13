@php
    $variant = $variant ?? 'default';
@endphp

<div class="mockup-frame {{ $variant === 'hero' ? 'mockup-frame--hero' : '' }}"
     role="img"
     aria-label="Sample inventory dashboard showing workshop stock levels with low-stock indicators.">
    <div class="mockup-frame__bar" aria-hidden="true">
        <span class="dot r"></span>
        <span class="dot y"></span>
        <span class="dot g"></span>
        <span class="mockup-url">app.madlogstore.test/inventory</span>
    </div>

    <div class="mockup-table-head" aria-hidden="true">
        <span>Part</span>
        <span>Bin</span>
        <span>Stock</span>
        <span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Brake Pad — Front (Toyota)</span>
        <span class="mockup-stock-row__bin">A-12</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--low">4</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-danger-subtle text-danger-emphasis">LOW</span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Engine Oil 5W-30 — 1L</span>
        <span class="mockup-stock-row__bin">B-04</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--ok">47</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-success-subtle text-success-emphasis">OK</span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Air Filter — Compact</span>
        <span class="mockup-stock-row__bin">C-07</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--ok">12</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-success-subtle text-success-emphasis">OK</span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Spark Plug — NGK Standard</span>
        <span class="mockup-stock-row__bin">A-03</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--ok">118</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-success-subtle text-success-emphasis">OK</span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Coolant — 1L Concentrate</span>
        <span class="mockup-stock-row__bin">D-02</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--ok">6</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-warning-subtle text-warning-emphasis">NEAR MIN</span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Timing Belt — 1.6L Petrol</span>
        <span class="mockup-stock-row__bin">E-11</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--low">2</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-danger-subtle text-danger-emphasis">LOW</span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Brake Fluid DOT-4 — 1L</span>
        <span class="mockup-stock-row__bin">B-09</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--ok">33</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-success-subtle text-success-emphasis">OK</span></span>
    </div>

    <div class="mockup-stock-row">
        <span class="fw-medium">Wiper Blade 22"</span>
        <span class="mockup-stock-row__bin">F-01</span>
        <span class="mockup-stock-row__qty mockup-stock-row__qty--low">0</span>
        <span class="mockup-stock-row__status"><span class="badge text-bg-danger-subtle text-danger-emphasis">OUT</span></span>
    </div>

    <p class="mockup-footer mb-0">Sample data — for demonstration only.</p>
</div>