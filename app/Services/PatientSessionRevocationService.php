<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * DEPLA Phase 2 - patient session/token revocation (driver-independent).
 *
 * WHY NOT REMEMBER-TOKEN VERSIONING
 * ---------------------------------
 * A natural design is to bump a version counter on the users row at
 * revocation and stamp the version into the session at login; the middleware
 * then rejects any session whose version is older. That was rejected here
 * because every password-reset / first-time-setup flow in this application
 * already rotates `remember_token` (NewPasswordController:80,
 * PatientSetupTokenService::consume:172) - tying the revocation check to the
 * remember token would therefore log out a patient the moment they complete a
 * legitimate password reset or setup, an unintended side effect.
 *
 * Instead this service keeps a per-user revocation timestamp in the cache and
 * compares it against a `patient_auth_at` marker the session carries. The two
 * values are independent of the password / remember_token lifecycle, so
 * password resets and setup never touch revocation.
 *
 * TLL vs SESSION LIFETIME
 * -----------------------
 * The marker is written with a TTL of 24 h, at least as long as the default
 * session lifetime (SESSION_LIFETIME=120 minutes). If the cache entry ever
 * expires while an old session is still alive, `revokedAt()` returns null and
 * the middleware fails OPEN for that window - an accepted, bounded risk that
 * trades a small revocation gap for zero false-positive logouts. The gap can
 * only occur at most once per 24 h and only for a session that outlives both
 * the TTL and the session lifetime.
 *
 * DRIVER INDEPENDENCE
 * -------------------
 * The cache store is whatever the application already uses (database in
 * production, array in tests). No new table and no schema change is
 * introduced.
 */
class PatientSessionRevocationService
{
    /**
     * Session key holding the unix timestamp of the patient's most recent
     * successful authentication (form login or remember-me re-auth).
     */
    public const SESSION_MARKER_KEY = 'patient_auth_at';

    private const CACHE_PREFIX = 'depl:patient_session_revoked:';

    /** Hours the cache marker survives (>= SESSION_LIFETIME). */
    private const MARKER_TTL_HOURS = 24;

    /**
     * Record that the patient's live access has been revoked NOW.
     *
     * Called AFTER the deactivate/reactivate transaction commits so a rolled
     * back transaction never leaves a stale revocation marker behind.
     *
     * Uses microtime (sub-second) rather than time() so a login and a
     * deactivation that occur within the same wall-clock second are still
     * correctly ordered: the revocation timestamp is always strictly later
     * than any authentication that preceded it, and a fresh login after
     * reactivation is always strictly later than the revocation.
     */
    public function markRevoked(User $user): void
    {
        Cache::put(
            self::CACHE_PREFIX.$user->getKey(),
            microtime(true),
            now()->addHours(self::MARKER_TTL_HOURS)
        );
    }

    /**
     * Microtime (float, sub-second) of the last revocation, or null when none
     * is recorded.
     */
    public function revokedAt(User $user): ?float
    {
        $value = Cache::get(self::CACHE_PREFIX.$user->getKey());

        return $value === null ? null : (float) $value;
    }
}
