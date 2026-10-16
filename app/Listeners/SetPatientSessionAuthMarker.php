<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\PatientSessionRevocationService;
use Illuminate\Auth\Events\Login;

/**
 * DEPLA Phase 2 - stamp the patient session with the time of authentication.
 *
 * Fired by the framework for BOTH form login (SessionGuard::login via
 * Auth::attempt) and remember-me re-auth (SessionGuard::userFromRecaller
 * calls updateSession + fireLoginEvent). One listener therefore covers every
 * path a patient can become authenticated, without editing the login
 * controller or the guard.
 *
 * Only patient-role users receive the marker: Admin and Staff sessions are
 * left completely untouched so patient lifecycle revocation can never touch
 * them. The Login event carries no request object, so the session store is
 * resolved through the session() helper - during a web request this is the
 * current request's started session.
 */

class SetPatientSessionAuthMarker
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || $user->role !== 'patient') {
            return;
        }

        if (! app()->bound('session')) {
            return;
        }

        session()->put(
            PatientSessionRevocationService::SESSION_MARKER_KEY,
            microtime(true)
        );
    }
}
