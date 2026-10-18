<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientAppointmentCancelRequest;
use App\Http\Requests\PatientAppointmentRescheduleRequest;
use App\Http\Requests\PatientAppointmentStoreRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentManagementService;
use App\Services\ClinicAvailabilityService;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DEPLA Phase 3B.3 — Patient Portal online appointment requests.
 *
 * The patient identity is resolved ONLY from the authenticated user's own
 * `patientAccount` hasOne link (User.user_id -> patients.id) — never from
 * a URL parameter, query string or request body — so a logged-in patient
 * can only ever see and create appointments for their own record. The
 * exact same resolveOwnPatient() refusal used by PatientPortalController
 * is repeated here (and the route middleware chain already refused
 * unlinked/archived accounts upstream).
 *
 * Eligibility is enforced by the route middleware chain
 * ['auth', 'patient-portal-account', 'must-change-password', 'no-store']
 * (identical to dashboard/My Profile); the role checks below keep the
 * clinic-role redirect/403 behaviour and add defence in depth.
 *
 * This controller NEVER reads patient_id, status, source, confirmed_*
 * or reviewed_* from the request. Store() passes only the three validated
 * fields into AppointmentManagementService::createOnlineRequest, which
 * assigns every server-owned column. The service is the same one the
 * Admin/Staff calendar uses, so schedule rules cannot drift between the
 * two surfaces, and a request created here appears on the existing
 * calendar automatically (same appointments table, source = online,
 * status = pending).
 *
 * No walk-in creation or review capability (confirm/decline/cancel by
 * staff/complete) exists here by design — those remain Admin/Staff-only
 * (Phase 3B.2B). Phase 3D.2B adds the two PATIENT cancellation paths:
 * a DIRECT cancel for pending online appointments and an approval-gated
 * cancellation REQUEST for confirmed online appointments. The appointment
 * is always resolved through the OWN patient's relation (foreign id =
 * 404) and the service re-asserts ownership, online source, status and
 * the scheduled-start cutoff inside its transaction.
 *
 * Phase 3B.4: after a request commits, every active Admin/Staff account
 * is notified of the new ONLINE request (walk-ins never pass through
 * here). The patient is never notified about their own submission.
 * Phase 3D.2B follows the same convention: both patient cancellation
 * paths notify Admin/Staff after commit and never notify the patient
 * about their own action.
 */
class PatientAppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentManagementService $service,
        private readonly SystemNotificationService $notifications,
        private readonly ClinicAvailabilityService $availability,
    ) {
    }

    /**
     * GET /patient/appointments/slots?date=YYYY-MM-DD — JSON slot catalog
     * for the booking date picker (Phase 2). Patient-safety scoped: the
     * payload contains only slot times and public state labels (available
     * / occupied / past / unavailable) plus the clinic-closed flag —
     * NEVER another patient's identity, appointment id, notes, confirmed
     * time or any other patient row. Unauthorized roles follow the same
     * portalUserOrAbort() refusal as every other action here. Invalid
     * date => 422 with the safe DomainException message.
     */
    public function slots(Request $request): JsonResponse
    {
        $this->portalUserOrAbort($request);

        try {
            $payload = $this->availability->slotsForDate((string) $request->query('date', ''));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($payload);
    }

    /**
     * GET /patient/appointments — the authenticated patient's own list,
     * grouped into Pending / Upcoming Confirmed / History sections.
     */
    public function index(Request $request): View
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        // Manila "today" anchor for the upcoming/history split. The global
        // application timezone stays UTC — only this display split is
        // clinic-anchored, matching the calendar controller's policy.
        $today = Carbon::now(AppointmentManagementService::CLINIC_TIMEZONE)->toDateString();

        $pending = $patient->appointments()
            ->where('status', Appointment::STATUS_PENDING)
            ->orderBy('requested_date')
            ->orderBy('requested_time')
            ->limit(20)
            ->get();

        $upcoming = $patient->appointments()
            ->with(['pendingChangeRequest', 'latestCancellationRequest', 'latestRescheduleRequest'])
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->where(function ($query) use ($today) {
                $query->whereDate('confirmed_date', '>=', $today)
                    ->orWhere(function ($or) use ($today) {
                        $or->whereNull('confirmed_date')
                            ->whereDate('requested_date', '>=', $today);
                    });
            })
            ->orderBy('confirmed_date')
            ->orderBy('confirmed_time')
            ->limit(20)
            ->get();

        $history = $patient->appointments()
            ->with(['pendingChangeRequest', 'latestCancellationRequest', 'latestRescheduleRequest'])
            ->where(function ($query) use ($today) {
                $query->whereIn('status', [
                    Appointment::STATUS_COMPLETED,
                    Appointment::STATUS_CANCELLED,
                    Appointment::STATUS_DECLINED,
                ])->orWhere(function ($or) use ($today) {
                    // A confirmed appointment whose schedule is already past
                    // moves to history (it was never completed/cancelled —
                    // the clinic may simply not have closed it out yet).
                    $or->where('status', Appointment::STATUS_CONFIRMED)
                        ->where(function ($qq) use ($today) {
                            $qq->whereDate('confirmed_date', '<', $today)
                                ->orWhere(function ($or2) use ($today) {
                                    $or2->whereNull('confirmed_date')
                                        ->whereDate('requested_date', '<', $today);
                                });
                        });
                });
            })
            ->orderByDesc('requested_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('patient.appointments.index', [
            'patient' => $patient,
            'pending' => $pending,
            'upcoming' => $upcoming,
            'history' => $history,
            'clinicToday' => $today,
        ]);
    }

    /**
     * GET /patient/appointments/create — the request form. No appointment
     * data is loaded here; the form posts to store().
     */
    public function create(Request $request): View
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        return view('patient.appointments.create', [
            'patient' => $patient,
            'clinicToday' => Carbon::now(AppointmentManagementService::CLINIC_TIMEZONE)->toDateString(),
        ]);
    }

    /**
     * POST /patient/appointments — create ONE pending online request.
     *
     * Only the three validated fields are forwarded. DomainException from
     * the service (invalid schedule caught at the write boundary, or an
     * active duplicate found inside the transaction) is surfaced as a
     * field-adjacent error with the typed input preserved, and no
     * notification is sent because the transaction never committed.
     *
     * Phase 3B.4: once the createOnlineRequest() transaction has committed
     * (the service has returned normally), every active Admin/Staff
     * account is notified of the new ONLINE request. Walk-in creation is
     * a separate clinic-side path and never reaches this controller. No
     * audit entry is written here (online requests stay unaudited, as in
     * Phase 3B.3) and the patient is not notified about their own
     * submission — the success redirect already confirms receipt.
     */
    public function store(PatientAppointmentStoreRequest $request): RedirectResponse
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        try {
            $appointment = $this->service->createOnlineRequest(
                $patient,
                $request->validated('appointment_type'),
                $request->validated('requested_date'),
                $request->validated('requested_time')
            );
        } catch (DomainException $exception) {
            // withInput preserves the typed schedule across the redirect.
            return back()->withInput()->withErrors(['appointment' => $exception->getMessage()]);
        }

        $this->notifications->notifyAppointmentRequested($appointment);

        return redirect()
            ->route('patient.appointments.index')
            ->with('success', 'Your appointment request has been submitted. Please wait for confirmation from the clinic.');
    }

    /**
     * POST /patient/appointments/{appointmentId}/cancel — Phase 3D.2B:
     * DIRECT cancellation of the patient's OWN PENDING ONLINE appointment.
     *
     * The appointment is resolved through the own patient's relation
     * (a missing id OR another patient's id both resolve to 404) and the
     * service re-asserts ownership, online source, pending status and the
     * scheduled-start cutoff inside its lockForUpdate transaction. A
     * DomainException (walk-in source, wrong status, cutoff passed, stale
     * row) surfaces as an inline error with no state change, no audit
     * row and no notification - the transaction never committed.
     *
     * After a successful commit the active Admin/Staff accounts are
     * notified; the patient is never notified about their own action.
     */
    public function cancel(PatientAppointmentCancelRequest $request, int $appointmentId): RedirectResponse
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        $appointment = $patient->appointments()->whereKey($appointmentId)->firstOrFail();

        try {
            $this->service->cancelByPatient(
                $appointment,
                $patient,
                $user,
                $request->validated('reason')
            );
        } catch (DomainException $exception) {
            // Phase 3D.4B (P2): the shared portal page renders MANY
            // cards, so a bare 'reason' error would repaint onto every
            // cancellation form on it. Flash the TARGETED appointment id
            // plus a form marker ('cancel' = the pending direct-cancel
            // form) so the Blade partial shows the refusal and the typed
            // reason on that card only. Old input is WHITELISTED to the
            // reason field - a password or any other field a client
            // appended to the POST can never enter the session.
            return back()
                ->withInput($request->only(['reason']))
                ->with('errorAppointmentId', $appointment->id)
                ->with('errorForm', 'cancel')
                ->withErrors(['reason' => $exception->getMessage()]);
        }

        $this->notifications->notifyPatientCancelledPendingAppointment($appointment);

        return redirect()
            ->route('patient.appointments.index')
            ->with('success', 'Your appointment request has been cancelled.');
    }

    /**
     * POST /patient/appointments/{appointmentId}/cancellation-requests —
     * Phase 3D.2B: approval-gated cancellation REQUEST against the
     * patient's OWN CONFIRMED ONLINE appointment.
     *
     * Same ownership resolution as cancel() (own relation, foreign id =
     * 404). The service creates ONE pending change-request row under the
     * appointment row lock; the appointment itself stays CONFIRMED with
     * its confirmed schedule untouched until Admin/Staff review (a later
     * step). A DomainException (walk-in, wrong status, cutoff passed,
     * pending request already exists) surfaces as an inline error with
     * no request row, no audit row and no notification.
     *
     * After a successful commit the active Admin/Staff accounts are
     * notified; the patient is never notified about their own action.
     */
    public function cancellationRequest(PatientAppointmentCancelRequest $request, int $appointmentId): RedirectResponse
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        $appointment = $patient->appointments()->whereKey($appointmentId)->firstOrFail();

        try {
            $changeRequest = $this->service->requestAppointmentCancellation(
                $appointment,
                $patient,
                $user,
                $request->validated('reason')
            );
        } catch (DomainException $exception) {
            // Phase 3D.4B (P2): same card-scoping as the direct cancel
            // above, with the 'cancellation-request' form marker so the
            // two cancellation forms can never paint each other's
            // failures. Only the typed reason is re-flashed as old input.
            return back()
                ->withInput($request->only(['reason']))
                ->with('errorAppointmentId', $appointment->id)
                ->with('errorForm', 'cancellation-request')
                ->withErrors(['reason' => $exception->getMessage()]);
        }

        $this->notifications->notifyAppointmentCancellationRequested($changeRequest);

        return redirect()
            ->route('patient.appointments.index')
            ->with('success', 'Your cancellation request has been submitted. Your appointment stays confirmed until the clinic reviews your request.');
    }

    /**
     * DEPLA Phase 3D.3B Step 1 - POST patient.appointments.rescheduleRequest.
     *
     * Lets the patient propose a NEW schedule for their own CONFIRMED
     * ONLINE appointment. Ownership: own patient relation + whereKey
     * firstOrFail (foreign id = 404, same as cancel()). The FormRequest
     * validates only the PROPOSED date/time/reason fields - the
     * appointment type is resolved server-side from the patient's own
     * appointment row (never from input).
     *
     * The service creates ONE pending change-request row (type=reschedule)
     * under the appointment lock and does NOT touch the appointment
     * itself: confirmed schedule and status stay intact until
     * Admin/Staff review (a later step). A DomainException (walk-in,
     * wrong status, cutoff passed, identical-to-current proposal,
     * active duplicate, pending request already exists) surfaces as an
     * inline error on the 'appointment' key with no request row, no
     * audit row and no notification.
     *
     * NOTE (user-approved decision K3, now implemented in Step 3): the
     * active Admin/Staff clinic-notification for a new reschedule
     * submission dispatches AFTER the service transaction commits
     * (event_key = appointment-reschedule-requested:{requestId}),
     * exactly like the cancellation-requested notification in 3D.2B.
     * A refused submission returns before that line, so it can never
     * produce a notification. The patient is never notified about
     * their own submission.
     */
    public function rescheduleRequest(PatientAppointmentRescheduleRequest $request, int $appointmentId): RedirectResponse
    {
        $user = $this->portalUserOrAbort($request);
        $patient = $this->resolveOwnPatient($user);

        $appointment = $patient->appointments()->whereKey($appointmentId)->firstOrFail();

        try {
            $changeRequest = $this->service->requestAppointmentReschedule(
                $appointment,
                $patient,
                $user,
                (string) $request->validated('proposed_date'),
                $request->validated('proposed_time'),
                $request->validated('reason')
            );
        } catch (DomainException $exception) {
            // errorAppointmentId scopes the error to the card this post
            // targeted (Phase 3D.3B Step 4 - the shared portal page
            // renders many cards, so a bare 'appointment' error could
            // otherwise appear on all of them).
            //
            // Phase 3D.4B (P2): old input is WHITELISTED to the four
            // fields the reschedule form actually posts (plus the 'form'
            // marker the partial uses to re-open this specific form),
            // instead of the whole request, so a forged POST can never
            // park a password or any other injected field in the session.
            return back()
                ->withInput($request->only(['proposed_date', 'proposed_time', 'reason', 'form']))
                ->with('errorAppointmentId', $appointment->id)
                ->withErrors(['appointment' => $exception->getMessage()]);
        }

        $this->notifications->notifyAppointmentRescheduleRequested($changeRequest);

        return redirect()
            ->route('patient.appointments.index')
            ->with('success', 'Your reschedule request has been submitted. Your appointment stays confirmed at its current schedule until the clinic reviews your request.');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals — identity resolution (same pattern as PatientPortalController)
    |--------------------------------------------------------------------------
    */

    /**
     * Non-patient roles are denied here because these pages expose and
     * create patient-owned appointment data. The portal middleware
     * deliberately passes clinic roles through (so the dashboard can
     * redirect them), so the explicit check stays.
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
     * Resolve the record that belongs to THIS login, or refuse. Inherits
     * Patient's SoftDeletes scope, so an archived record resolves to null
     * even if the upstream middleware were reordered. The refusal reuses
     * the Phase 2D §12 wording and discloses nothing about which case
     * occurred.
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
