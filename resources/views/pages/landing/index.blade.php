<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
<head>
    @include('pages.landing.partials.head')
</head>
<body class="bg-light text-body">

    <a href="#main" class="skip-link">Skip to main content</a>

    @include('pages.landing.partials.navbar')

    <main id="main" tabindex="-1">
        @include('pages.landing.partials.hero')
        @include('pages.landing.partials.social-proof')
        @include('pages.landing.partials.problem-solution')
        @include('pages.landing.partials.features')
        @include('pages.landing.partials.workflow')
        @include('pages.landing.partials.dashboard-showcase')
        @include('pages.landing.partials.pricing')
        @include('pages.landing.partials.faq')
        @include('pages.landing.partials.final-cta')
    </main>

    @include('pages.landing.partials.footer')

    @stack('scripts')
</body>
</html>