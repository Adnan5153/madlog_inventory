<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title>Staff · Madlog Store</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light text-body">
    <main class="container py-5" style="max-width: 760px;">
        <header class="mb-4">
            <h1 class="display-6 fw-semibold mb-2">Staff dashboard</h1>
            <p class="text-muted mb-0">
                Welcome, <strong>{{ auth()->user()?->name }}</strong>.
                You're signed in as a staff member.
            </p>
        </header>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Foundation under construction</h2>
                <p class="mb-3">
                    This is the placeholder staff dashboard. The day-to-day
                    parts, inventory, purchase-order, and job-card workflows
                    will be built on top of these models in the next phase.
                </p>
                <ul class="mb-3">
                    <li>Browse and issue parts</li>
                    <li>Receive purchase orders</li>
                    <li>Cycle counts and adjustments</li>
                    <li>Open and close job cards</li>
                </ul>
                <a href="{{ route('home') }}" class="btn btn-warning">
                    <i class="bi bi-arrow-left me-1"></i> Back to site
                </a>
            </div>
        </div>
    </main>
</body>
</html>
