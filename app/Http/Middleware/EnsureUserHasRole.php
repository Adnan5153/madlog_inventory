<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic role-guarded middleware. Use when a route needs to accept
 * multiple roles (e.g. admin AND staff) but you want to block public
 * visitors.
 *
 * Usage in routes:
 *   Route::middleware('role:admin')->group(...)
 *   Route::middleware('role:staff')->group(...)
 *   Route::middleware('role:admin,staff')->group(...)
 *
 * Registered alias: 'role'
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($user->isGlobalAdmin()) {
            // Global admins pass any role check.
            return $next($request);
        }

        if ($user->role === null || ! in_array($user->role, $roles, true)) {
            abort(403, 'You do not have the required role to access this page.');
        }

        return $next($request);
    }
}
