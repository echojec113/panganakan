<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PresentsPregnancyRecords;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DEPLA Phase 3E.2 — Patient Portal "My Medical Records" (read-only),
 * extended by Phase 3E.3 with Ultrasound Records, Birth Plan, Delivery
 * Records and Baby Information.
 *
 * Scope is deliberately narrow: ONE method, ONE GET route, NO writes.
 * The page renders only the pregnancy row currently linked to the
 * authenticated login through `patientAccount` (users.id ->
 * patients.user_id). Nothing here reads an ID from the URL, query string
 * or request body, so a patient can never address another record.
 *
 * HISTORICAL PREGNANCIES REMAIN OUT OF SCOPE FOR THIS CONTROLLER. The
 * Phase 3H.4 Pregnancy History pages live in
 * PatientPregnancyHistoryController and authorize historical episodes
 * through STRICT user_pregnancy_links membership; this controller keeps
 * its original pointer-only contract untouched. Phase 3H.4 extracted the
 * seven-section presentation logic (column allow-lists and builders) into
 * the shared PresentsPregnancyRecords trait so both controllers render
 * identical, unchanged patient-facing fields - visible output, ordering,
 * formatting and null handling are byte-identical to Phase 3E.2/3E.3.
 *
 * PRIVACY. Clinical rows are fetched with explicit column allow-lists
 * (PresentsPregnancyRecords) and then copied into plain presentation
 * arrays before the view is built, so the Blade receives no Eloquent model
 * at all. Risk assessment, ML predictions, factor evidence, assessment
 * metadata, decision metadata, staff verification fields, referral
 * internals and free-text clinical notes are excluded at the SQL level and
 * are therefore unreachable even if a future template edit forgets a
 * guard.
 *
 * Eligibility is enforced upstream by the route middleware chain
 * ['auth', 'patient-portal-account', 'must-change-password', 'no-store']
 * (guests -> login, unlinked/archived -> 403, flagged -> password change,
 * responses never cacheable). The role check and resolveOwnPatient()
 * below repeat the same defence-in-depth contract used by
 * PatientPortalController and PatientAppointmentController.
 */
class PatientMedicalRecordsController extends Controller
{
    use PresentsPregnancyRecords;

    /**
     * Read-only presentation of the linked pregnancy. Strictly GET.
     *
     * Back-link/header view variables intentionally ABSENT: the shared
     * seven-tab template defaults to the original "Back to Dashboard"
     * destination and the "currently linked pregnancy" wording, so this
     * page's rendered output is unchanged from Phase 3E.2/3E.3. Only
     * PatientPregnancyHistoryController passes the historical overrides.
     */
    public function index(Request $request): View
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        return view('patient.medical-records.index', $this->presentEpisode($patient));
    }

    /**
     * Non-patient roles are denied here because this page exposes patient
     * clinical data. The portal middleware deliberately passes clinic
     * roles through (so the dashboard can redirect them), so the explicit
     * check stays — same contract as PatientPortalController::profile().
     */
    private function portalUserOrAbort(Request $request): User
    {
        $user = $request->user();

        if ($user === null || $user->role !== 'patient') {
            abort(403);
        }

        return $user;
    }

    /**
     * Resolve the record that belongs to THIS login, or refuse.
     *
     * Uses the `patientAccount` hasOne relationship itself (with an
     * explicit column allow-list), so an unlinked account resolves to
     * null and an archived account is hidden by Patient's SoftDeletes
     * global scope. The refusal reuses the Phase 2D wording and discloses
     * nothing about which case occurred.
     */
    private function resolveOwnPatient(User $user): Patient
    {
        $patient = $this->resolveCurrentEpisodeWithRecords($user);

        if ($patient === null) {
            abort(
                403,
                'Your patient account is currently unavailable. Please contact the clinic for assistance.'
            );
        }

        return $patient;
    }
}
