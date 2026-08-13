<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Madlog Store') : config('app.name', 'Madlog Store') }}
</title>

<meta name="description" content="Madlog Store — Inventory Management for Mechanical Workshops. Track every part, tool, and stock movement from the storeroom to the job card." />
<meta name="theme-color" content="#0f172a" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/landing/landing.scss', 'resources/js/landing.js'])