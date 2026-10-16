<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\PatientSessionRevocationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * DEPLA Phase 2 - reject a patient session that predates the most recent
 * deactivation/reactivation revocation.
 *
 * The lifecycle controller deletes the patient's rows from the `sessions`
 * table (database driver) and rotates the remember token, which already
 * invalidates the session and the remember-me cookie. This middleware is the
 * driver-independent safety net for the small window between "session row
 * still resolvable" and "revocation marker written", and for any session
 * driver that does not honour a server-side row deletion.
 *
 * DESIGN NOTES
 * ------------
 * - Login key detection is deliberately NOT used to decide pass/fail. The
 *   session marker (`patient_auth_at`) is written by the Login event listener
 *   (SetPatientSessionAuthMarker) at the moment of every patient
 *   authentication - form login AND remember-me re-auth - so the middleware
 *   can compare timestamps directly without inspecting the login key.
 *
 * - A missing marker on a live patient session is treated as 0 (fail-closed
 *   for pre-existing sessions that predate this feature). A patient who has
 *   never been revoked has `revokedAt() === null` and is never affected.
 *
 * - Non-patient roles and guests pass through untouched: Admin and Staff
 *   sessions are never invalidated by patient lifecycle actions.
 */

class EnsurePatientSessionNotRevoked
{
    public function __construct(
        private readonly PatientSessionRevocationService $revocation,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== 'patient') {
            return $next($request);
        }

        $revokedAt = $this->revocation->revokedAt($user);

        if ($revokedAt === null) {
            return $next($request);
        }

        $authAt = (float) $request->session()->get(
            PatientSessionRevocationService::SESSION_MARKER_KEY,
            0.0
        );

        if ($authAt < $revokedAt) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Your session has ended. Please sign in again.');
        }

        return $next($request);
    }
}
