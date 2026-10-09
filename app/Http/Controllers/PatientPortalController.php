<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DEPLA Phase 3A — Patient-facing portal (dashboard + read-only profile).
 *
 * Deliberately small: two read-only methods, no writes, no listings, no
 * clinical relationships. The patient identity is resolved ONLY from the
 * authenticated user's own `patientAccount` hasOne link (User.user_id ->
 * patients.id) — never from a URL parameter, query string or request body —
 * so a logged-in patient can only ever see their own record.
 *
 * Eligibility itself is enforced upstream by the route middleware chain
 * ['auth', 'patient-portal-account', 'must-change-password', 'no-store']
 * (Phase 2C): guests are sent to login, unlinked/archived patient accounts
 * get the friendly 403, flagged accounts are sent to the mandatory password
 * change form, and responses are never cacheable. The role checks below are
 * the same behaviour the original portal route closure had, kept for
 * Admin/Staff (redirect) and unknown roles (403), plus defence in depth on
 * the new profile page.
 */
class PatientPortalController extends Controller
{
    /**
     * GET /patient-portal (route name kept: patient.portal.coming-soon).
     *
     * The route name is intentionally preserved: Phase 2B/2C login
     * redirects, the guest middleware destination and several existing
     * tests all point at it. Only the CONTENT behind it changed in 3A
     * (holding page -> dashboard).
     */
    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->role === 'patient') {
            return view('patient.dashboard', [
                'patient' => $this->resolveOwnPatient($user),
            ]);
        }

        if (in_array($user->role, ['admin', 'staff'], true)) {
            return redirect()->route('dashboard');
        }

        abort(403);
    }

    /**
     * GET /patient/profile — strictly read-only, patient-only.
     *
     * No ID is accepted anywhere: the same `patientAccount` link is used,
     * so `/patient/profile?patient_id=…` (or any other parameter) is
     * ignored by construction. Non-patient roles are denied here because,
     * unlike the dashboard, this page exposes patient personal data and the
     * shared portal middleware deliberately passes clinic roles through.
     */
    public function profile(Request $request): View
    {
        $user = $request->user();

        if ($user === null || $user->role !== 'patient') {
            abort(403);
        }

        return view('patient.profile', [
            'patient' => $this->resolveOwnPatient($user),
            'loginEmail' => (string) $user->email,
        ]);
    }

    /**
     * Resolve the record that belongs to THIS login, or refuse.
     *
     * `patientAccount` is hasOne(Patient::class, 'user_id') — the Phase 2A
     * link — and inherits Patient's SoftDeletes global scope, so an archived
     * record resolves to null here even if the upstream middleware were ever
     * reordered. The refusal reuses the Phase 2D §12 wording and discloses
     * nothing about which case occurred.
     */
    private function resolveOwnPatient(User $user): Patient
    {
        $patient = $user->patientAccount;

        if ($patient === null) {
            abort(
                403,
                'Your patient account is currently unavailable. Please contact the clinic for assistance.'
            );
        }

        return $patient;
    }
}
