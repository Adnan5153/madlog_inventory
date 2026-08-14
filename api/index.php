<?php
/**
 * Vercel PHP entry point for the Madlog Store Laravel application.
 *
 * Vercel's PHP runtime runs each request in an isolated serverless
 * function. Routing every path through here + pointing at
 * Laravel's own front controller (`public/index.php`) lets the
 * framework handle URL rewriting, CSRF, sessions, and routing
 * exactly as it would on `php artisan serve`.
 *
 * The file lives at `api/index.php` rather than at the repo root so
 * Vercel's `vercel-php` builder can pick it up cleanly without
 * colliding with the static `index.html` at the project root (the
 * static landing page is NOT served by this deployment; the client
 * demo only needs the auth-gated admin and staff panels).
 */
require __DIR__ . '/../public/index.php';
