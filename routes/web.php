<?php

use App\Http\Controllers\RoleDashboardRedirectController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.landing.index')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The named `dashboard` route is what Fortify redirects to after a
    // successful login. We replace the Fortify starter view with a
    // role-based redirect so admins land on /admin and staff on /staff.
    Route::get('dashboard', RoleDashboardRedirectController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
