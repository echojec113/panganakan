<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outer clinic-only authorization boundary (Phase 1 security hardening).
 *
 * Allows ONLY authenticated users whose role is exactly 'admin' or 'staff'.
 * Every other role value — 'patient', unknown future roles, null/missing —
 * is denied with 403 before the route's controller is reached.
 *
 * This middleware is an ADDITIONAL boundary: existing Admin-only and
 * Staff-only restrictions (AdminMiddleware, StaffMiddleware, in-controller
 * checks) remain in force and are never weakened by this layer.
 */
class EnsureClinicStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'staff'], true)) {
            abort(403);
        }

        return $next($request);
    }
}
