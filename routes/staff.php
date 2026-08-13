<?php

use App\Http\Controllers\Staff\StaffDashboardController;
use Illuminate\Support\Facades\Route;

/*
 * Staff routes. Restricted to users with role=staff. Staff are always
 * workshop-scoped (workshop_id is required for staff in practice).
 * Per-workshop scoping is enforced by WorkshopScope and policies.
 */
Route::middleware(['auth', 'verified', 'staff'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/', StaffDashboardController::class)->name('dashboard');
    });
