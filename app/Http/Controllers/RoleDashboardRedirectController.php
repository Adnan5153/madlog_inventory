<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resolves the post-login landing route based on the caller's role.
 *
 * Fortify sends the user to the route named `dashboard` after a
 * successful login; we replace the Fortify starter `dashboard` view
 * with this controller so that admins (global or workshop-scoped)
 * land on the real admin dashboard, while staff land on the staff
 * dashboard. Users without an admin/staff role fall back to the
 * landing page.
 */
class RoleDashboardRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isStaff()) {
            return redirect()->route('staff.dashboard');
        }

        return redirect()->route('home');
    }
}
