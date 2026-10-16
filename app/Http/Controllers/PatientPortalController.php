<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentManagementService;
use App\Services\GestationalAgeCalculator;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Support\TrimesterClassifier;
use Carbon\Carbon;
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
     * Phase 3F — read-only dashboard widget data. Both services are
     * stateless, write nothing and are reused exactly as the clinic-side
     * code uses them (no formula or threshold was changed).
     */
    public function __construct(
        private readonly GestationalAgeCalculator $gestationalAgeCalculator,
        private readonly PregnancyOutcomeMonitoringService $pregnancyOutcomeMonitoring,
    ) {
    }

    /**
     * GET /patient-portal (route name kept: patient.portal.coming-soon).
     *
     * The route name is intentionally preserved: Phase 2B/2C login
     * redirects, the guest middleware destination and several existing
     * tests all point at it. Only the CONTENT behind it changed in 3A
     * (holding page -> dashboard).
     *
     * Phase 3F: besides `patient`, the view now receives two PLAIN
     * presentation arrays — `nextAppointment` (?array) and
     * `maternityTracker` (array) — built exclusively from the linked
     * pregnancy row and its own appointments, with explicit field
     * allow-lists. The dashboard Blade is unchanged in this sprint; the
     * arrays are consumed by the Phase 3G widget UI.
     */
    public function dashboard(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        if ($user->role === 'patient') {
            $patient = $this->resolveOwnPatient($user);

            // Single clinic-anchored "today" (Asia/Manila) for every
            // dashboard widget decision — the same policy the patient
            // appointments page uses. The global app timezone stays UTC.
            $clinicToday = Carbon::now(AppointmentManagementService::CLINIC_TIMEZONE);

            return view('patient.dashboard', [
                'patient' => $patient,
                'nextAppointment' => $this->presentNextAppointment($patient, $clinicToday),
                'maternityTracker' => $this->presentMaternityTracker($patient, $clinicToday),
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

    /*
    |--------------------------------------------------------------------------
    | Phase 3F — dashboard widget presentation (read-only, allow-listed)
    |--------------------------------------------------------------------------
    | Both helpers take ONLY the already-resolved own Patient plus the
    | clinic-anchored "today". No request input is read, no ID is accepted,
    | and the output is a plain array (never an Eloquent model), so the
    | Blade layer can only ever render the fields listed here.
    */

    /**
     * The patient's single next CONFIRMED appointment, or null.
     *
     * Selection (own relation only, soft deletes respected via the model's
     * global scope):
     *   1. status = confirmed (pending is a request, never shown here);
     *   2. effective date = confirmed_date ?? requested_date — the SAME
     *      expression is used for the "today or later" filter and the
     *      ordering, so a NULL confirmed_date can never smuggle a past
     *      confirmed schedule in front of a future requested one;
     *   3. effective date >= clinic today (Asia/Manila);
     *   4. order by effective date ASC, then effective time
     *      (confirmed_time ?? requested_time) ASC with missing times last,
     *      then id ASC as the deterministic tie-break;
     *   5. first row only.
     *
     * Presentation allow-list: type label (existing Appointment::TYPE_LABELS
     * accessor), effective date, effective time ("Time not recorded" when
     * neither time column is set) and the patient-friendly status label.
     * Never exposed: patient_id, source, reviewer identity, decline or
     * cancellation reasons, timestamps, or any other patient's rows.
     * Appointments store no appointment-specific provider, so no doctor or
     * physician field exists here by design.
     *
     * @return array{appointment_type_label: string, effective_date_display: string, effective_time_display: string, status_label: string}|null
     */
    private function presentNextAppointment(Patient $patient, Carbon $clinicToday): ?array
    {
        $appointment = $patient->appointments()
            ->select([
                'id',
                'appointment_type',
                'requested_date',
                'requested_time',
                'confirmed_date',
                'confirmed_time',
                'status',
            ])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->whereRaw('COALESCE(confirmed_date, requested_date) >= ?', [$clinicToday->toDateString()])
            ->orderByRaw('COALESCE(confirmed_date, requested_date) ASC')
            ->orderByRaw('CASE WHEN COALESCE(confirmed_time, requested_time) IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByRaw('COALESCE(confirmed_time, requested_time) ASC')
            ->orderBy('id')
            ->first();

        if ($appointment === null) {
            return null;
        }

        $effectiveDate = Carbon::parse(
            $appointment->confirmed_date ?? $appointment->requested_date
        );
        $effectiveTime = $appointment->confirmed_time ?? $appointment->requested_time;
        $effectiveTime = is_string($effectiveTime) && trim($effectiveTime) !== ''
            ? $effectiveTime
            : null;

        return [
            'appointment_type_label' => $appointment->type_label,
            'effective_date_display' => $effectiveDate->format('l, F j, Y'),
            // Same clock convention as the appointment card partial: the
            // stored HH:MM:SS truncated to HH:MM (24-hour).
            'effective_time_display' => $effectiveTime === null
                ? 'Time not recorded'
                : substr($effectiveTime, 0, 5),
            'status_label' => 'Confirmed',
        ];
    }

    /**
     * The linked pregnancy episode's patient-safe tracker facts.
     *
     * Source of truth: the OWN patients row only (one row = one pregnancy
     * episode; the login's `patientAccount` link). Reused calculations:
     *   - GestationalAgeCalculator (weeks from stored LMP to the Manila
     *     clinic date; null when either date is missing/invalid),
     *   - TrimesterClassifier::label (null-safe trimester wording),
     *   - PregnancyOutcomeMonitoringService::daysUntilOrPastEdd (signed
     *     days from the existing monitoring service — EDD passed NEVER
     *     implies delivery).
     *
     * The signed-day calculation is anchored by passing a UTC-midnight
     * Carbon built from the MANILA calendar date: patients.edd is a pure
     * calendar date stored as a UTC-midnight cast, so aligning the
     * reference to the same midnight representation keeps the day
     * difference an exact integer (no off-by-one from the 8-hour Manila
     * offset), while the date string itself stays clinic-anchored. The
     * anchor is expressed in the SAME timezone as the patient's own edd
     * Carbon instance, so the integer day difference stays exact whether
     * the date cast materialises in the app timezone (production) or in a
     * frozen test clock's timezone (SQLite feature tests).
     *
     * DELIVERED rows get a completion-state array: no countdown, no
     * current weeks/trimester, delivery date only when actually recorded.
     * Missing facts stay null for the view to render as unavailable —
     * LMP, EDD and gestational age are NEVER invented or derived into one
     * another. No risk level, ML prediction, referral state, staff note,
     * factor evidence, outcome confirmation provenance, progress bar or
     * percentage is computed or returned.
     *
     * @return array{
     *     is_delivered: bool,
     *     status_label: string,
     *     lmp_display: string|null,
     *     edd_display: string|null,
     *     weeks_along_display: string|null,
     *     trimester_label: string|null,
     *     edd_countdown_display: string|null,
     *     delivery_date_display: string|null
     * }
     */
    private function presentMaternityTracker(Patient $patient, Carbon $clinicToday): array
    {
        $isDelivered = $patient->isDelivered();

        $statusLabel = match (true) {
            $isDelivered => 'Delivered',
            $patient->status === 'ONGOING' => 'Ongoing',
            default => 'Status not recorded',
        };

        $lmpDisplay = $this->formatTrackerDate($patient->lmp);
        $eddDisplay = $this->formatTrackerDate($patient->edd);

        if ($isDelivered) {
            return [
                'is_delivered' => true,
                'status_label' => $statusLabel,
                'lmp_display' => $lmpDisplay,
                'edd_display' => $eddDisplay,
                'weeks_along_display' => null,
                'trimester_label' => null,
                'edd_countdown_display' => null,
                'delivery_date_display' => $this->formatTrackerDate($patient->delivery_date),
            ];
        }

        $gestationalAge = $this->gestationalAgeCalculator->calculate(
            $patient->lmp?->format('Y-m-d'),
            $clinicToday->toDateString()
        );

        // Anchor the signed day count to the clinic calendar date (Asia/
        // Manila) expressed at midnight in the SAME timezone as the
        // patient's own edd Carbon instance, so the service's startOfDay
        // diff is always an exact integer of calendar days — in production
        // (UTC app timezone) and under frozen test clocks alike.
        $daysUntilEdd = $patient->edd === null
            ? null
            : $this->pregnancyOutcomeMonitoring->daysUntilOrPastEdd(
                $patient,
                Carbon::parse($clinicToday->toDateString(), $patient->edd->tzName)->startOfDay()
            );

        return [
            'is_delivered' => false,
            'status_label' => $statusLabel,
            'lmp_display' => $lmpDisplay,
            'edd_display' => $eddDisplay,
            'weeks_along_display' => $gestationalAge === null
                ? null
                : number_format($gestationalAge, 1, '.', '') . ' weeks',
            'trimester_label' => TrimesterClassifier::label($gestationalAge),
            'edd_countdown_display' => $this->presentEddCountdown($daysUntilEdd),
            'delivery_date_display' => null,
        ];
    }

    /**
     * Patient-friendly EDD countdown wording for ONGOING pregnancies.
     * A passed EDD is presented as "contact the clinic" — never as a
     * delivery, and never with a week count or progress figure.
     */
    private function presentEddCountdown(?int $daysUntilEdd): string
    {
        if ($daysUntilEdd === null) {
            return 'Due date not recorded';
        }

        if ($daysUntilEdd > 0) {
            return $daysUntilEdd . ' days to go';
        }

        if ($daysUntilEdd === 0) {
            return 'Due date is today';
        }

        return 'Estimated due date has passed — please contact the clinic';
    }

    /**
     * Existing portal date convention (M d, Y); missing dates stay null so
     * the view can render an honest unavailable state.
     */
    private function formatTrackerDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('M d, Y');
    }
}
