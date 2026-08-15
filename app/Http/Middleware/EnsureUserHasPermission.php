<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission gate middleware.
 *
 * Usage:
 *   Route::middleware('permission:products.view')->...
 *   Route::middleware('permission:products.view,inventory.adjust')->...  // ANY
 *   Route::middleware('permission_all:products.view,inventory.adjust')->... // ALL
 *
 * Registered alias: `permission` (single-ability `ANY`), `permission_all`.
 *
 * Resolution delegates to User::hasPermission(), which already grants
 * the `super-admin` fast-path for users whose `role=admin`.
 */
class EnsureUserHasPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  string  ...$abilities  One or more permission names.
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if ($abilities === []) {
            // No abilities requested is a misconfiguration; deny.
            abort(403, 'No permission specified for route guard.');
        }

        foreach ($abilities as $ability) {
            if ($user->hasPermission($ability)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this resource.');
    }
}
