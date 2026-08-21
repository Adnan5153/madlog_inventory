<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="theme-color" content="#4f46e5" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<meta name="description" content="Madlog Store administration console." />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{--
    Apply the persisted sidebar state synchronously in <head> so the
    sidebar renders at the correct width on first paint (no flash).
    This mirrors what admin.js does at module-eval time but runs even
    earlier, before the Vite bundle has been parsed.
--}}
<script>
    (function () {
        try {
            if (localStorage.getItem('madlog.admin.sidebarCollapsed') === '1') {
                document.documentElement.classList.add('admin-sidebar-collapsed');
            }
            // Pre-paint table density so the first frame matches what
            // the user picked. Avoids a flash when `comfortable` is the
            // persisted default.
            var density = localStorage.getItem('madlog.table.density');
            if (density === 'compact' || density === 'comfortable') {
                document.documentElement.dataset.density = density;
            }
        } catch (e) {}
    })();
</script>

@stack('head')