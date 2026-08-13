<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict a route to authenticated staff (admin OR staff role).
 *
 * The middleware only enforces "must be signed in as either an
 * administrator or a workshop staff member". Per-workshop scoping is
 * enforced by the WorkshopScope global scope and the model's policies.
 *
 * The granular permission system in P6 will replace this with a
 * `permission:` middleware; for now any staff member can browse the
 * admin area, while create/update/delete is gated by the model
 * policies (which check `isAdmin()`).
 *
 * Registered alias: 'admin'
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->isAuthenticatedStaff()) {
            abort(403, 'This area is restricted to staff members.');
        }

        return $next($request);
    }
}
