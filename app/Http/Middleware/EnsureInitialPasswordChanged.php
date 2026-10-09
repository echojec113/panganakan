<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2C - mandatory first-login password change enforcement.
 *
 * A patient-role user whose account still carries must_change_password = true
 * may only reach the dedicated change-initial-password endpoints and logout;
 * every other authenticated route redirects to the change form. The check is
 * deliberately scoped to role 'patient' so this middleware can be attached to
 * shared 'auth' groups without altering any Admin/Staff behaviour, and so an
 * unknown role is never silently promoted or blocked by this rule.
 */
class EnsureInitialPasswordChanged
{
    /**
     * Route names a flagged patient may still use.
     *
     * Logout stays reachable so a patient who cannot supply the current
     * password is never trapped on the form, and the two change routes are
     * listed so that applying this middleware to them as well can never
     * produce a redirect loop back onto themselves.
     */
    private const EXEMPT_ROUTE_NAMES = [
        'logout',
        'patient.change-initial-password.edit',
        'patient.change-initial-password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->role === 'patient'
            && (bool) $user->must_change_password
            && ! in_array($request->route()?->getName(), self::EXEMPT_ROUTE_NAMES, true)) {
            return redirect()->route('patient.change-initial-password.edit');
        }

        return $next($request);
    }
}
