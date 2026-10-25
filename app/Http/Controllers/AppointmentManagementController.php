<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppointmentCancelRequest;
use App\Http\Requests\AppointmentCompleteRequest;
use App\Http\Requests\AppointmentConfirmRequest;
use App\Http\Requests\AppointmentDeclineRequest;
use App\Http\Requests\AppointmentWalkInStoreRequest;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentManagementService;
use App\Services\ClinicAvailabilityService;
use App\Services\SystemNotificationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * DEPLA Phase 3B.2B - Admin/Staff appointment management (mutations).
 *
 * Thin controller: every state transition and walk-in creation is
 * delegated to AppointmentManagementService (transaction + row lock +
 * audit). Authorization is layered: the clinic middleware (auth + admin|
 * staff) plus each FormRequest's authorize(). Redirects go back() so the
 * caller keeps their calendar view/date/filters; DomainExceptions surface
 * as a safe session error, never a 500.
 *
 * Phase 3B.4: after each SUCCESSFUL transition (post-commit, after the
 * service has returned and any DomainException has been caught) the
 * patient is notified through SystemNotificationService. Notifications are
 * never sent for walk-in creation (clinic staff already see the record
 * they just created), and a failed/rolled-back transition short-circuits
 * on the DomainException before this line is reached.
 */
class AppointmentManagementController extends Controller
{
    public function __construct(
        private readonly AppointmentManagementService $service,
        private readonly SystemNotificationService $notifications,
        private readonly ClinicAvailabilityService $availability,
    ) {
    }

    /**
     * GET /appointments/slots?date=YYYY-MM-DD — JSON slot catalog for the
     * clinic date picker (Phase 2). Admin/staff only via the 'clinic'
     * route middleware. No patient-identifying data in the payload: only
     * slot times, states and the closure flag. Invalid date => 422 with
     * the safe DomainException message (never a 500).
     *
     * Phase 3 (prenatal follow-up edit): optional ignore_appointment_id
     * lets the edit form see its OWN linked appointment's slot as
     * selectable again (the slot it already occupies). Staff-only route,
     * integer-coerced, and it only ever REMOVES one row from the read -
     * it never marks a foreign slot available, never returns appointment
     * data, and the authoritative write path re-validates occupancy with
     * its own explicit ignore argument. The PATIENT endpoint deliberately
     * does not accept this parameter.
     */
    public function slots(Request $request): JsonResponse
    {
        $rawIgnore = $request->query('ignore_appointment_id');
        $ignoreAppointmentId = null;

        if ($rawIgnore !== null && $rawIgnore !== '') {
            if (! is_scalar($rawIgnore) || ! ctype_digit((string) $rawIgnore)) {
                return response()->json(['message' => 'The ignore appointment reference is invalid.'], 422);
            }

            $ignoreAppointmentId = (int) $rawIgnore;
        }

        try {
            $payload = $this->availability->slotsForDate(
                (string) $request->query('date', ''),
                null,
                $ignoreAppointmentId
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($payload);
    }

    public function confirm(AppointmentConfirmRequest $request, Appointment $appointment): RedirectResponse
    {
        try {
            $confirmed = $this->service->confirm(
                $appointment,
                $request->user(),
                $request->validated('confirmed_date'),
                $request->validated('confirmed_time')
            );
        } catch (DomainException $exception) {
            // withInput preserves the typed schedule; errorAppointmentId
            // lets the calendar reopen the details modal with the error.
            return back()
                ->withInput()
                ->with('errorAppointmentId', $appointment->id)
                ->withErrors(['appointment' => $exception->getMessage()]);
        }

        $this->notifications->notifyAppointmentStatusChanged($confirmed, Appointment::STATUS_CONFIRMED);

        return back()->with('success', 'Appointment confirmed.');
    }

    public function decline(AppointmentDeclineRequest $request, Appointment $appointment): RedirectResponse
    {
        try {
            $declined = $this->service->decline(
                $appointment,
                $request->user(),
                $request->validated('decline_reason')
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        $this->notifications->notifyAppointmentStatusChanged($declined, Appointment::STATUS_DECLINED);

        return back()->with('success', 'Appointment declined.');
    }

    public function cancel(AppointmentCancelRequest $request, Appointment $appointment): RedirectResponse
    {
        try {
            $cancelled = $this->service->cancel(
                $appointment,
                $request->user(),
                $request->validated('cancellation_reason')
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        $this->notifications->notifyAppointmentStatusChanged($cancelled, Appointment::STATUS_CANCELLED);

        return back()->with('success', 'Appointment cancelled.');
    }

    public function complete(AppointmentCompleteRequest $request, Appointment $appointment): RedirectResponse
    {
        try {
            $completed = $this->service->complete($appointment, $request->user());
        } catch (DomainException $exception) {
            return back()->withErrors(['appointment' => $exception->getMessage()]);
        }

        $this->notifications->notifyAppointmentStatusChanged($completed, Appointment::STATUS_COMPLETED);

        return back()->with('success', 'Appointment marked as completed.');
    }

    public function walkInStore(AppointmentWalkInStoreRequest $request): RedirectResponse
    {
        try {
            $this->service->createWalkIn(
                $request->user(),
                (int) $request->validated('patient_id'),
                $request->validated('appointment_type'),
                $request->validated('appointment_date'),
                $request->validated('appointment_time')
            );
        } catch (DomainException $exception) {
            // withInput preserves the typed schedule across the redirect.
            return back()->withInput()->withErrors(['walk_in' => $exception->getMessage()]);
        }

        return back()->with('success', 'Walk-in appointment created.');
    }

    /**
     * Minimal JSON patient picker for the walk-in modal (Admin + Staff,
     * same clinic boundary as the calendar itself). Follows the existing
     * server-side search pattern from PatientController::index (name parts
     * + patient id) and excludes archived rows. Returns only the fields
     * needed to identify and select an existing patient - no clinical
     * data, no login-account data.
     */
    public function patientSearch(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $query = Patient::query()
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'age', 'contact_number'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10);

        if ($search !== '') {
            $patientIdSearch = preg_replace('/^PT-?/i', '', strtoupper($search));

            $query->where(function ($searchQuery) use ($search, $patientIdSearch) {
                $searchQuery
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");

                if (ctype_digit($patientIdSearch)) {
                    $searchQuery->orWhere('id', (int) $patientIdSearch);
                }
            });
        }

        $patients = $query->get()->map(fn (Patient $patient) => [
            'id' => $patient->id,
            'full_name' => trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter([
                $patient->first_name,
                $patient->middle_name,
                $patient->last_name,
            ])))),
            'age' => $patient->age,
            'contact_number' => $patient->contact_number,
        ]);

        return response()->json(['patients' => $patients]);
    }
}
