<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Placeholder staff dashboard. Same approach as the admin one —
 * minimal Bootstrap-styled landing so the route has a real target.
 * The real staff experience (parts, inventory, POs, job cards) will
 * be built out in a later task.
 */
class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('staff.dashboard');
    }
}
