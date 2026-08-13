<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to authenticated staff members (role = 'staff').
 *
 * Admins are NOT staff — they have their own /admin area. Mechanics,
 * foremen, and storekeepers are staff. Only workshop-scoped admins
 * can be granted 'staff' via shared access if needed; that's left as
 * a policy decision per workshop.
 *
 * Registered alias: 'staff'
 */
class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->isStaff()) {
            abort(403, 'This area is restricted to staff members.');
        }

        return $next($request);
    }
}
