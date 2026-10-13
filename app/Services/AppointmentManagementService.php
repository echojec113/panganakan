<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * DEPLA Phase 3B.2B - authoritative owner of every appointment state
 * transition and of walk-in creation.
 *
 * The controller never mutates an appointment directly: each operation
 * runs in ONE DB::transaction with the appointment row re-read under
 * lockForUpdate(), so two simultaneous actions on the same appointment
 * are serialized and the loser sees the already-changed status and is
 * refused with a DomainException - a stale action can never overwrite
 * the first one.
 *
 * Permitted transitions (validated server-side, never from request input):
 *   pending  -> confirmed  (Confirm)
 *   pending  -> declined   (Decline)
 *   confirmed -> cancelled (Cancel)
 *   confirmed -> completed (Mark Completed)
 * Everything else is refused. Status/source/reviewer are never read from
 * the request; they are assigned explicitly inside the service.
 *
 * Provisional scheduling rule (requires clinic confirmation): for checkup
 * and counseling, a confirmed/walk-in start time must be at or after
 * 08:00 and STRICTLY before 17:00 Philippine time, Monday through Sunday.
 * The clinic has not clarified whether 5:00 PM is the latest START or the
 * CLOSING time, so the conservative interpretation (no start AT 17:00) is
 * used until confirmed. No fixed session duration is invented and there is
 * no capacity limit - multiple appointments may share a date/time.
 *
 * Delivery requests are accepted 24/7 with an optional time (date-only
 * is allowed). A delivery confirmation is scheduling/request approval
 * ONLY: it never guarantees a bed, never creates an admission and never
 * writes any occupancy record. Delivery requests are never auto-rejected
 * based on bed state - no bed state exists in this system.
 *
 * Cancellation deliberately does NOT overwrite reviewed_by/reviewed_at
 * (those record the original confirm/decline review); the cancellation
 * actor is captured in the audit log instead - no new columns.
 *
 * Past-schedule rule (bug fix): a NEW schedule (walk-in creation or a
 * pending confirmation) may never be in the past. Comparisons use the
 * clinic timezone (Asia/Manila) anchored at the WRITE boundary - the
 * current time is re-read inside the transaction, so a stale form that
 * crossed midnight (or sat open past the chosen time) is still refused.
 * Full local date+time is compared (never date and time separately):
 *   - date-only (delivery, no time): today or a future date is allowed;
 *     an earlier date is refused.
 *   - date + time: the schedule is allowed when it is at or after the
 *     current minute; an earlier minute is refused. No time is ever
 *     silently shifted forward and no date is auto-adjusted.
 * The application's global timezone stays UTC - only this rule is
 * Manila-anchored, matching the calendar controller's policy.
 *
 * Phase 3D.2B (patient cancellation): two PATIENT-initiated operations
 * join the staff transitions above - cancelByPatient() (pending online
 * -> cancelled, direct) and requestAppointmentCancellation() (confirmed
 * online -> pending change request, approval-gated). Both re-check
 * ownership, online source, status and the scheduled-start cutoff
 * INSIDE their transaction under the same lockAppointment() discipline,
 * and both write their audit rows in-transaction. Walk-in rows are
 * refused on the patient path and keep the staff workflow above
 * untouched.
 *
 * Phase 3D.2C (change-request review): approveCancellationRequest()
 * and declineCancellationRequest() let Admin/Staff review a pending
 * patient cancellation request. Both lock the appointment row FIRST
 * and the request row second (same lock-first ordering as
 * requestAppointmentCancellation, so the two paths serialize), then
 * revalidate request type/status, online source, appointment status
 * and the scheduled-start cutoff before touching anything. Approve
 * cancels the appointment (copying the PATIENT's reason into
 * cancellation_reason - the reviewer never re-types it) and stamps
 * reviewed_by/reviewed_at/review_note on the REQUEST row only; the
 * appointment's original reviewer is never overwritten. Decline keeps
 * the appointment confirmed with its schedule intact and requires a
 * 3-1000 explanation stored on the request row. Approve enforces the
 * same Manila cutoff the patient path uses; Decline deliberately does
 * NOT (it changes nothing on the appointment, so a stale request can
 * always be cleaned up without stranding it).
 *
 * Phase 3D.2C auto-resolution: cancel() and complete() close any
 * still-pending change request in the SAME transaction - a pending
 * cancellation auto-approves when the staff cancels the appointment
 * directly, and everything else (cancellation + completion, or any
 * reschedule-type request) auto-declines. This is an audit-visible
 * service invariant, not a schema constraint, and it deliberately
 * sends NO extra patient notification: the existing appointment
 * status notification already covers the outcome.
 *
 * Phase 3D.3B Step 1 (patient reschedule request): requestAppointment
 * Reschedule() lets the owning patient propose a NEW schedule for their
 * own CONFIRMED ONLINE appointment without touching the appointment
 * row - the proposal lives on one appointment_change_requests row
 * (type=reschedule, status=pending) exactly like the cancellation
 * request of Phase 3D.2B. It re-checks ownership, online source,
 * confirmed status, the current-schedule cutoff and the one-pending
 * invariant under the same lockAppointment() discipline, then
 * validates the PROPOSED schedule with the shared validateSchedule()
 * (clinic hours + Manila future rule) and refuses two collision cases
 * (user-approved decisions K2): a proposal identical to the current
 * confirmed schedule, and a proposal that exactly matches another
 * ACTIVE (pending or confirmed) appointment of the same patient and
 * type. Delivery proposals are ALWAYS date-only (user-approved
 * decision K5) - any submitted time is ignored and proposed_time is
 * stored NULL. This step never mutates an appointment from a request
 * row; review happens in Step 2 below.
 *
 * Phase 3D.3B Step 2 (staff reschedule review): approveRescheduleRequest()
 * applies the patient's EXACT proposed schedule to the appointment
 * (K4 - no staff editing; delivery forced date-only so confirmed_time
 * becomes NULL per K5), revalidating BOTH the current appointment
 * cutoff and the proposed schedule (format/clinic hours/Manila future
 * rule) plus BOTH K2 collision guards under the appointment-then-
 * request lock order, with the active-duplicate guard acting as the
 * AUTHORITATIVE re-check at review time. declineRescheduleRequest()
 * requires the explanation and mutates nothing on the appointment, so
 * it deliberately skips the cutoff - an expired proposal can always be
 * declined as cleanup but never approved. Neither method notifies
 * anyone yet (outcome notifications belong to a later step); both
 * write their audit in-transaction and refuse BEFORE the first save on
 * any failure.
 */
class AppointmentManagementService
{
    /** Clinic opens (inclusive) - provisional, Mon-Sun. */
    public const CLINIC_OPEN_TIME = '08:00';

    /** Last permitted START is strictly BEFORE this (exclusive) - provisional. */
    public const CLINIC_LAST_START_TIME = '17:00';

    /** Timezone used for "now" in past-schedule checks. Not the app tz. */
    public const CLINIC_TIMEZONE = 'Asia/Manila';

    /**
     * Confirm a pending appointment request.
     *
     * @throws DomainException on invalid transition or invalid schedule
     */
    public function confirm(Appointment $appointment, User $actor, string $confirmedDate, ?string $confirmedTime): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $confirmedDate, $confirmedTime) {
            $locked = $this->lockAppointment($appointment);

            $this->assertStatus($locked, Appointment::STATUS_PENDING, 'confirm');

            $time = $this->validateSchedule($locked->appointment_type, $confirmedDate, $confirmedTime);

            $previous = $locked->status;

            $locked->confirmed_date = $confirmedDate;
            $locked->confirmed_time = $time;
            $locked->status = Appointment::STATUS_CONFIRMED;
            $locked->reviewed_by = $actor->id;
            $locked->reviewed_at = now();
            $locked->save();

            $this->audit($actor, $locked, 'APPOINTMENT_CONFIRMED', $previous, Appointment::STATUS_CONFIRMED, sprintf(
                'Appointment #%d for patient ID %d confirmed (%s, %s %s).',
                $locked->id,
                $locked->patient_id,
                $locked->appointment_type,
                $confirmedDate,
                $time ?? '(no time)'
            ));

            return $locked;
        });
    }

    /**
     * Decline a pending appointment request. The row is kept (history).
     *
     * @throws DomainException on invalid transition
     */
    public function decline(Appointment $appointment, User $actor, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $reason) {
            $locked = $this->lockAppointment($appointment);

            $this->assertStatus($locked, Appointment::STATUS_PENDING, 'decline');

            $reason = $this->normalizeReason($reason);
            $previous = $locked->status;

            $locked->status = Appointment::STATUS_DECLINED;
            $locked->decline_reason = $reason;
            $locked->reviewed_by = $actor->id;
            $locked->reviewed_at = now();
            $locked->save();

            $this->audit($actor, $locked, 'APPOINTMENT_DECLINED', $previous, Appointment::STATUS_DECLINED, sprintf(
                'Appointment #%d for patient ID %d declined. Reason: %s',
                $locked->id,
                $locked->patient_id,
                $reason
            ));

            return $locked;
        });
    }

    /**
     * Cancel a confirmed appointment.
     *
     * reviewed_by / reviewed_at are deliberately PRESERVED (they document
     * the original review); the cancellation actor lives in the audit log.
     *
     * @throws DomainException on invalid transition
     */
    public function cancel(Appointment $appointment, User $actor, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $reason) {
            $locked = $this->lockAppointment($appointment);

            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'cancel');

            $reason = $this->normalizeReason($reason);
            $previous = $locked->status;

            $locked->status = Appointment::STATUS_CANCELLED;
            $locked->cancellation_reason = $reason;
            $locked->save();

            $this->audit($actor, $locked, 'APPOINTMENT_CANCELLED', $previous, Appointment::STATUS_CANCELLED, sprintf(
                'Appointment #%d for patient ID %d cancelled by user ID %d. Reason: %s',
                $locked->id,
                $locked->patient_id,
                $actor->id,
                $reason
            ));

            // Phase 3D.2C: a direct staff cancellation makes any pending
            // cancellation request moot - auto-close it in the same
            // transaction (approved, matching outcome), never in a second
            // request. No extra patient notification is sent here (the
            // controller's notifyAppointmentStatusChanged covers it).
            $this->autoResolvePendingChangeRequests($locked, $actor, 'cancelled');

            return $locked;
        });
    }

    /**
     * Mark a confirmed appointment as completed (administrative workflow
     * completion only - never childbirth, admission or discharge).
     *
     * No automatic completion: only this explicit action completes a row.
     * No clinical side effects: no prenatal visit, birth plan, pregnancy
     * outcome, delivery or occupancy record is created.
     *
     * @throws DomainException on invalid transition
     */
    public function complete(Appointment $appointment, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor) {
            $locked = $this->lockAppointment($appointment);

            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'complete');

            $previous = $locked->status;

            $locked->status = Appointment::STATUS_COMPLETED;
            $locked->save();

            $this->audit($actor, $locked, 'APPOINTMENT_COMPLETED', $previous, Appointment::STATUS_COMPLETED, sprintf(
                'Appointment #%d for patient ID %d marked completed by user ID %d.',
                $locked->id,
                $locked->patient_id,
                $actor->id
            ));

            // Phase 3D.2C: completion makes any pending change request
            // moot - auto-decline it in the same transaction (the
            // appointment is finished; it can neither be cancelled nor
            // rescheduled). No extra patient notification is sent here
            // (the controller's notifyAppointmentStatusChanged covers it).
            $this->autoResolvePendingChangeRequests($locked, $actor, 'completed');

            return $locked;
        });
    }

    /**
     * Create a walk-in appointment for an EXISTING, non-archived patient.
     *
     * Walk-in status rule: an authorized Admin/Staff entry with an explicit
     * validated schedule is stored directly as CONFIRMED (requested ==
     * confirmed == the staff-entered schedule, full reviewer metadata).
     * A pending walk-in is never generated. Delivery requests may be
     * date-only (time intentionally null - never invented); checkup and
     * counseling always require a time inside the provisional clinic hours.
     *
     * source is ALWAYS walk_in and status is ALWAYS confirmed - neither is
     * read from the request. No patient row, no patient account, no clinical
     * record and no occupancy/admission record is created.
     *
     * @throws DomainException when the patient is missing or archived
     */
    public function createWalkIn(User $actor, int $patientId, string $appointmentType, string $date, ?string $time): Appointment
    {
        return DB::transaction(function () use ($actor, $patientId, $appointmentType, $date, $time) {
            $patient = Patient::find($patientId);

            if (! $patient) {
                throw new DomainException('The selected patient record does not exist or is archived.');
            }

            $time = $this->validateSchedule($appointmentType, $date, $time);

            $appointment = new Appointment();
            $appointment->patient_id = $patient->id;
            $appointment->appointment_type = $appointmentType;
            $appointment->source = Appointment::SOURCE_WALK_IN;
            $appointment->requested_date = $date;
            $appointment->requested_time = $time;
            $appointment->confirmed_date = $date;
            $appointment->confirmed_time = $time;
            $appointment->status = Appointment::STATUS_CONFIRMED;
            $appointment->reviewed_by = $actor->id;
            $appointment->reviewed_at = now();
            $appointment->save();

            $this->audit($actor, $appointment, 'WALK_IN_APPOINTMENT_CREATED', null, Appointment::STATUS_CONFIRMED, sprintf(
                'Walk-in appointment #%d created for patient ID %d (%s, %s %s).',
                $appointment->id,
                $patient->id,
                $appointmentType,
                $date,
                $time ?? '(no time)'
            ));

            return $appointment;
        });
    }

    /**
     * Create a PATIENT-SUBMITTED online appointment request (Phase 3B.3).
     *
     * Identity: the caller (controller) has already resolved the Patient
     * from the authenticated portal account's patientAccount link; this
     * method never reads a patient id from user input. status is ALWAYS
     * pending and source is ALWAYS online - neither is accepted as an
     * argument, so a forged status/source/reviewer/confirmed schedule can
     * never be persisted through this path. confirmed_date, confirmed_time,
     * reviewed_by and reviewed_at start NULL and are set only by the
     * staff confirm/decline transitions above.
     *
     * Schedule rules are the SAME validateSchedule() the staff path uses
     * (checkup/counseling 08:00-inclusive to 17:00-exclusive Mon-Sun with a
     * required time; delivery 24/7 with optional time; past schedules
     * refused in Asia/Manila at the write boundary) - no duplicated or
     * divergent clinic logic. The service repeats the past-schedule check
     * inside the transaction for defence in depth (the FormRequest already
     * reports it at the field level for the modal UX).
     *
     * Duplicate policy (provisional, server-side): within the transaction,
     * the same patient may not hold another ACTIVE (pending or confirmed)
     * request with the identical type + requested date + requested time.
     * Completed/cancelled/declined history never blocks a new request, and
     * there is no global one-per-day rule - two different patients may hold
     * the same schedule, and one patient may hold different types/times on
     * the same date. HONEST LIMITATION: this is a read-before-insert check
     * inside a transaction, NOT a database-enforced unique constraint - a
     * hard concurrency guarantee would need a schema change (unique index),
     * which Phase 3B.3 deliberately does not introduce. The check catches
     * sequential double-clicks and any request that starts after the first
     * commit; two truly simultaneous inserts could still race on MySQL.
     *
     * No admission, bed, occupancy or clinical record of any kind is
     * created - this is an operational request row only.
     *
     * @throws DomainException when the schedule is invalid or an active
     *                         duplicate already exists
     */
    public function createOnlineRequest(Patient $patient, string $appointmentType, string $date, ?string $time): Appointment
    {
        return DB::transaction(function () use ($patient, $appointmentType, $date, $time) {
            $time = $this->validateSchedule($appointmentType, $date, $time);

            $duplicate = Appointment::query()
                ->where('patient_id', $patient->id)
                ->where('appointment_type', $appointmentType)
                ->whereIn('status', [
                    Appointment::STATUS_PENDING,
                    Appointment::STATUS_CONFIRMED,
                ])
                ->whereDate('requested_date', $date)
                ->when(
                    $time === null,
                    fn ($query) => $query->whereNull('requested_time'),
                    fn ($query) => $query->where('requested_time', $time)
                )
                ->exists();

            if ($duplicate) {
                throw new DomainException(
                    'You already have an active appointment request for this exact schedule. Please wait for the clinic to review it, or choose a different date or time.'
                );
            }

            $appointment = new Appointment();
            $appointment->patient_id = $patient->id;
            $appointment->appointment_type = $appointmentType;
            $appointment->source = Appointment::SOURCE_ONLINE;
            $appointment->requested_date = $date;
            $appointment->requested_time = $time;
            $appointment->status = Appointment::STATUS_PENDING;
            // confirmed_date / confirmed_time / reviewed_by / reviewed_at /
            // decline_reason / cancellation_reason intentionally left NULL -
            // they belong to the staff review workflow, never to the patient.
            $appointment->save();

            return $appointment;
        });
    }

    /**
     * Phase 3D.2B - PATIENT direct cancellation of a PENDING online
     * appointment (no approval, no change-request row).
     *
     * Identity: the caller (controller) has already resolved the Patient
     * from the authenticated portal account's patientAccount link and
     * scoped the appointment query to that patient; this method RE-CHECKS
     * ownership, source, status and the scheduled-start cutoff inside the
     * transaction under lockForUpdate, so a direct service call or a
     * stale browser cannot bypass any rule. Status/source/schedule are
     * never read from client input - they are asserted here.
     *
     * Cancellation semantics mirror the staff cancel(): the row is kept
     * as history, cancellation_reason stores the patient's reason, and
     * reviewed_by/reviewed_at are deliberately NOT touched (a pending
     * appointment has no reviewer, and a cancellation never invents one).
     * The actor is the patient's own portal user - captured in the audit
     * log, not in a new column.
     *
     * A pending appointment cannot hold a change request (change requests
     * exist only for confirmed appointments), so no request cleanup runs
     * on this path.
     *
     * @throws DomainException on ownership/source/status/cutoff refusal
     */
    public function cancelByPatient(Appointment $appointment, Patient $patient, User $actor, string $reason): Appointment
    {
        return DB::transaction(function () use ($appointment, $patient, $actor, $reason) {
            $locked = $this->lockAppointment($appointment);

            $this->assertPatientOwnedOnline($locked, $patient);
            $this->assertStatus($locked, Appointment::STATUS_PENDING, 'cancel');
            $this->assertNotScheduledStartPassed($locked);

            $reason = $this->normalizeReason($reason);
            $previous = $locked->status;

            $locked->status = Appointment::STATUS_CANCELLED;
            $locked->cancellation_reason = $reason;
            $locked->save();

            $this->audit($actor, $locked, 'PATIENT_CANCELLED_PENDING', $previous, Appointment::STATUS_CANCELLED, sprintf(
                'Appointment #%d for patient ID %d cancelled directly by the patient (user ID %d). Reason: %s',
                $locked->id,
                $locked->patient_id,
                $actor->id,
                $reason
            ));

            return $locked;
        });
    }

    /**
     * Phase 3D.2B - PATIENT cancellation REQUEST against a CONFIRMED
     * online appointment (approval-gated: the appointment stays
     * confirmed until Admin/Staff approve in a later step).
     *
     * Creates ONE appointment_change_requests row (type=cancellation,
     * status=pending) under the SAME lock-first discipline: the
     * appointment row is locked BEFORE any request row is read or
     * written, so concurrent submissions serialize on the appointment
     * lock and the one-pending check is race-free (the approved design
     * deliberately uses NO database-level constraint).
     *
     * The confirmed schedule and appointment status are left completely
     * untouched - only the request row is created (plus its in-tx audit).
     * proposed_date/proposed_time stay NULL (cancellation carries no new
     * schedule). The patient's reason is validated (3-1000) and stored on
     * the request row; it reaches staff through the review UI, never
     * through a notification payload.
     *
     * @throws DomainException on ownership/source/status/cutoff refusal
     *                         or when another pending change request exists
     */
    public function requestAppointmentCancellation(Appointment $appointment, Patient $patient, User $actor, string $reason): AppointmentChangeRequest
    {
        return DB::transaction(function () use ($appointment, $patient, $actor, $reason) {
            $locked = $this->lockAppointment($appointment);

            $this->assertPatientOwnedOnline($locked, $patient);
            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'request cancellation for');
            $this->assertNotScheduledStartPassed($locked);

            $pendingRequest = AppointmentChangeRequest::query()
                ->where('appointment_id', $locked->id)
                ->where('status', AppointmentChangeRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->exists();

            if ($pendingRequest) {
                throw new DomainException(
                    'This appointment already has a change request awaiting clinic review. Please wait for the clinic to review it before submitting another request.'
                );
            }

            $reason = $this->normalizeReason($reason);

            $request = new AppointmentChangeRequest();
            $request->appointment_id = $locked->id;
            $request->type = AppointmentChangeRequest::TYPE_CANCELLATION;
            $request->proposed_date = null;
            $request->proposed_time = null;
            $request->reason = $reason;
            $request->status = AppointmentChangeRequest::STATUS_PENDING;
            $request->save();

            $this->audit($actor, $locked, 'PATIENT_REQUESTED_CANCELLATION', Appointment::STATUS_CONFIRMED, Appointment::STATUS_CONFIRMED, sprintf(
                'Patient (user ID %d) submitted a cancellation request for confirmed appointment #%d (change request #%d). Reason: %s',
                $actor->id,
                $locked->id,
                $request->id,
                $reason
            ));

            return $request;
        });
    }

    /**
     * Phase 3D.3B Step 1 - PATIENT reschedule REQUEST against their own
     * CONFIRMED ONLINE appointment (approval-gated: the appointment
     * stays confirmed with its current schedule until Admin/Staff review
     * in a later step).
     *
     * Creates ONE appointment_change_requests row (type=reschedule,
     * status=pending, proposed_date/proposed_time carrying the patient's
     * new preferred schedule) under the SAME lock-first discipline as
     * requestAppointmentCancellation: the appointment row is locked
     * BEFORE any request row is read or written, so concurrent
     * submissions (two reschedules, or a reschedule racing a
     * cancellation) serialize on the appointment lock and the
     * one-pending check is race-free. The appointment row itself is
     * NEVER touched here - confirmed_date, confirmed_time, status and
     * reviewed_* stay byte-identical (approved rule 9); the proposal
     * only moves the schedule when staff later approve.
     *
     * Eligibility re-checked INSIDE the transaction: ownership (the
     * caller's own patient), online source, confirmed status and the
     * current-schedule cutoff (assertNotScheduledStartPassed - same
     * Manila rule as cancellation: timed = strictly before the start
     * minute; date-only delivery = through the end of the scheduled
     * day). Pending appointments are refused (approved rule 3: cancel
     * the pending request and submit a new one instead).
     *
     * Proposed-schedule validation reuses the shared validateSchedule()
     * (format, provisional clinic hours for checkup/counseling, Manila
     * future rule) so the patient and staff surfaces cannot drift.
     * Delivery proposals are forced date-only (approved decision K5):
     * any submitted time is discarded and proposed_time is stored NULL.
     *
     * Two collision guards (approved decision K2, enforced here at
     * submission under the lock; the staff approval step will RE-check
     * the active-duplicate case authoritatively at review time):
     *   1. a proposal identical (same date, same time - date-only
     *      compares as null==null) to the appointment's CURRENT
     *      confirmed schedule is refused;
     *   2. a proposal that exactly matches the effective schedule of
     *      ANOTHER active (pending or confirmed) appointment of the
     *      same patient and type is refused.
     * The reason is OPTIONAL (approved rule 8): null/blank stores NULL;
     * when supplied it is trimmed and validated 3-1000.
     *
     * No admission, bed, occupancy or clinical record of any kind is
     * created - this is an operational request row only.
     *
     * @throws DomainException on ownership/source/status/cutoff refusal,
     *                         one-pending conflict, invalid or identical
     *                         proposed schedule, or active duplicate
     */
    public function requestAppointmentReschedule(Appointment $appointment, Patient $patient, User $actor, string $date, ?string $time, ?string $reason): AppointmentChangeRequest
    {
        return DB::transaction(function () use ($appointment, $patient, $actor, $date, $time, $reason) {
            $locked = $this->lockAppointment($appointment);

            $this->assertPatientOwnedOnline($locked, $patient);
            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'request rescheduling for');
            $this->assertNotScheduledStartPassed($locked);

            $pendingRequest = AppointmentChangeRequest::query()
                ->where('appointment_id', $locked->id)
                ->where('status', AppointmentChangeRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->exists();

            if ($pendingRequest) {
                throw new DomainException(
                    'This appointment already has a change request awaiting clinic review. Please wait for the clinic to review it before submitting another request.'
                );
            }

            // Approved decision K5: delivery reschedules are ALWAYS
            // date-only - any submitted time is discarded before
            // validation so it can never be stored.
            if ($locked->appointment_type === Appointment::TYPE_DELIVERY_REQUEST) {
                $time = null;
            }

            $time = $this->validateSchedule($locked->appointment_type, $date, $time);

            // Collision guard 1 (K2): a proposal identical to the
            // CURRENT confirmed schedule is not a reschedule. The
            // current schedule is the one the patient can see:
            // confirmed schedule for a confirmed row (time null means
            // date-only delivery), requested schedule as the defensive
            // fallback when no confirmed date exists.
            $currentDate = ($locked->confirmed_date ?? $locked->requested_date)?->toDateString();
            $currentTime = $locked->confirmed_date !== null
                ? ($locked->confirmed_time !== null ? substr((string) $locked->confirmed_time, 0, 5) : null)
                : ($locked->requested_time !== null ? substr((string) $locked->requested_time, 0, 5) : null);

            if ($date === $currentDate && $time === $currentTime) {
                throw new DomainException(
                    'The proposed schedule is the same as your current confirmed schedule. Please choose a different date or time.'
                );
            }

            // Collision guard 2 (K2): the proposal must not exactly
            // match ANOTHER active appointment of the same patient and
            // type (this appointment is excluded). The other row's
            // effective schedule is its confirmed schedule when it has
            // one, otherwise its requested schedule.
            $this->assertNoConflictingActiveAppointment($locked, $date, $time);

            $reason = $reason !== null && trim($reason) !== '' ? $this->normalizeReason($reason) : null;

            $request = new AppointmentChangeRequest();
            $request->appointment_id = $locked->id;
            $request->type = AppointmentChangeRequest::TYPE_RESCHEDULE;
            $request->proposed_date = $date;
            $request->proposed_time = $time;
            $request->reason = $reason;
            $request->status = AppointmentChangeRequest::STATUS_PENDING;
            $request->save();

            $this->audit($actor, $locked, 'PATIENT_REQUESTED_RESCHEDULE', Appointment::STATUS_CONFIRMED, Appointment::STATUS_CONFIRMED, sprintf(
                'Patient (user ID %d) submitted a reschedule request for confirmed appointment #%d (change request #%d). Proposed schedule: %s %s. Reason: %s',
                $actor->id,
                $locked->id,
                $request->id,
                $date,
                $time ?? '(date only)',
                $reason ?? '(none)'
            ));

            return $request;
        });
    }

    /**
     * Phase 3D.2C - Admin/Staff APPROVAL of a pending patient cancellation
     * request: the appointment is cancelled and the PATIENT's original
     * reason is copied into cancellation_reason (the reviewer never
     * re-types it). Optional staff note (3-1000 or null) is stored on the
     * REQUEST row's review_note; the appointment's original reviewed_by /
     * reviewed_at (the confirmer) are deliberately NOT touched - the
     * reviewer of this decision lives on the request row and in the audit.
     *
     * Same lock-first ordering as requestAppointmentCancellation: the
     * appointment row is locked BEFORE the request row, so a concurrent
     * staff cancel/complete (auto-resolution) or a concurrent approve/
     * decline serialize on the appointment lock and the loser sees the
     * already-changed status and is refused. Revalidated INSIDE the
     * transaction: request type must be cancellation, request must still
     * be pending, appointment must still be confirmed, source must be
     * online (walk-ins never use change requests) and the scheduled
     * start must not have passed yet (same Manila cutoff the patient
     * path uses - an expired appointment can no longer be cancelled
     * through this workflow; use the regular cancel action instead).
     *
     * @throws DomainException on stale/invalid state or cutoff refusal
     */
    public function approveCancellationRequest(AppointmentChangeRequest $changeRequest, User $actor, ?string $note): Appointment
    {
        return DB::transaction(function () use ($changeRequest, $actor, $note) {
            $locked = $this->lockAppointment($changeRequest->appointment);
            $lockedRequest = $this->lockChangeRequest($changeRequest->id);

            if ($lockedRequest->type !== AppointmentChangeRequest::TYPE_CANCELLATION) {
                throw new DomainException('Only cancellation requests can be approved here.');
            }

            if ($lockedRequest->status !== AppointmentChangeRequest::STATUS_PENDING) {
                throw new DomainException(sprintf(
                    'This change request can no longer be approved - its current status is "%s". The page has been refreshed with the latest state.',
                    $lockedRequest->status
                ));
            }

            $this->assertOnlineSource($locked);
            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'approve_cancellation');
            $this->assertNotScheduledStartPassed($locked);

            $note = $note !== null && trim($note) !== '' ? trim($note) : null;
            $previous = $locked->status;

            $locked->status = Appointment::STATUS_CANCELLED;
            $locked->cancellation_reason = $lockedRequest->reason;
            $locked->save();

            $lockedRequest->status = AppointmentChangeRequest::STATUS_APPROVED;
            $lockedRequest->reviewed_by = $actor->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->review_note = $note;
            $lockedRequest->save();

            $this->audit($actor, $locked, 'CANCELLATION_REQUEST_APPROVED', $previous, Appointment::STATUS_CANCELLED, sprintf(
                'Patient cancellation request #%d approved by user ID %d: appointment #%d for patient ID %d cancelled with the patient\'s reason. Staff note: %s',
                $lockedRequest->id,
                $actor->id,
                $locked->id,
                $locked->patient_id,
                $note ?? '(none)'
            ));

            return $locked;
        });
    }

    /**
     * Phase 3D.2C - Admin/Staff DECLINE of a pending patient cancellation
     * request: the appointment stays CONFIRMED with its schedule fully
     * intact (a decline changes nothing about the appointment row), and
     * the REQUIRED explanation (3-1000) is stored on the REQUEST row's
     * review_note for the portal card to display.
     *
     * Same lock-first ordering and in-transaction revalidation as
     * approve (request type cancellation + still pending; appointment
     * still confirmed + online source). Deliberately NO scheduled-start
     * cutoff here: declining keeps the appointment exactly as it was, so
     * a stale request whose appointment has already started can still be
     * declined as cleanup - refusing would strand it as pending forever
     * (staff cancel/complete auto-resolves only while those actions
     * remain legal).
     *
     * @throws DomainException on stale/invalid state
     */
    public function declineCancellationRequest(AppointmentChangeRequest $changeRequest, User $actor, string $note): AppointmentChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $actor, $note) {
            $locked = $this->lockAppointment($changeRequest->appointment);
            $lockedRequest = $this->lockChangeRequest($changeRequest->id);

            if ($lockedRequest->type !== AppointmentChangeRequest::TYPE_CANCELLATION) {
                throw new DomainException('Only cancellation requests can be declined here.');
            }

            if ($lockedRequest->status !== AppointmentChangeRequest::STATUS_PENDING) {
                throw new DomainException(sprintf(
                    'This change request can no longer be reviewed - its current status is "%s". The page has been refreshed with the latest state.',
                    $lockedRequest->status
                ));
            }

            $this->assertOnlineSource($locked);
            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'decline_cancellation');

            $note = $this->normalizeReason($note);

            $lockedRequest->status = AppointmentChangeRequest::STATUS_DECLINED;
            $lockedRequest->reviewed_by = $actor->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->review_note = $note;
            $lockedRequest->save();

            $this->audit($actor, $locked, 'CANCELLATION_REQUEST_DECLINED', Appointment::STATUS_CONFIRMED, Appointment::STATUS_CONFIRMED, sprintf(
                'Patient cancellation request #%d declined by user ID %d: appointment #%d for patient ID %d stays confirmed. Explanation: %s',
                $lockedRequest->id,
                $actor->id,
                $locked->id,
                $locked->patient_id,
                $note
            ));

            return $lockedRequest;
        });
    }

    /**
     * Phase 3D.3B Step 2 - Admin/Staff APPROVAL of a pending patient
     * reschedule request: the patient's EXACT proposed schedule is
     * applied to the appointment's confirmed schedule (approved decision
     * K4 - staff CANNOT edit the proposal; the proposal lives on the
     * request row and is applied verbatim). Optional staff note
     * (3-1000 or null) is stored on the REQUEST row's review_note; the
     * appointment's original reviewed_by/reviewed_at (the confirmer)
     * are deliberately NOT touched - the approver's identity lives on
     * the request row and in the audit.
     *
     * Same lock-first ordering as every other review path (appointment
     * row BEFORE request row), so a concurrent staff cancel/complete
     * (auto-resolution) or a concurrent approve/decline serialize on
     * the appointment lock and the loser sees the already-changed
     * state. Revalidated INSIDE the transaction:
     *   1. request type must be reschedule;
     *   2. request must still be pending (stale repeats refused);
     *   3. appointment still confirmed + online-sourced;
     *   4. the CURRENT appointment cutoff must not have passed
     *      (assertNotScheduledStartPassed - same Manila rule as
     *      submission);
     *   5. the PROPOSED schedule must still be valid - the shared
     *      validateSchedule() rechecks format, provisional clinic hours
     *      (checkup/counseling) and the Manila future rule, so an
     *      EXPIRED proposal cannot be approved (decline remains the
     *      cleanup path for stale proposals);
     *   6. collision guard 1: a proposal identical to the current
     *      confirmed schedule is refused (it is not a reschedule);
     *   7. collision guard 2 - the AUTHORITATIVE K2 re-check: an exact
     *      duplicate of another active (pending/confirmed) same-patient
     *      same-type appointment is refused. A conflicting appointment
     *      may have been created BETWEEN submission and review, so the
     *      submission-time check alone is not enough.
     *
     * K5: a delivery request is applied DATE-ONLY - confirmed_time is
     * ALWAYS written NULL (any stray stored proposed_time is ignored,
     * exactly like at submission). The status stays confirmed; only
     * confirmed_date/confirmed_time move. Any refusal throws BEFORE the
     * first save, so a failed approval writes nothing (single
     * transaction).
     *
     * @throws DomainException on stale/invalid state, current cutoff,
     *                         expired/invalid proposal, identical
     *                         proposal or exact active duplicate
     */
    public function approveRescheduleRequest(AppointmentChangeRequest $changeRequest, User $actor, ?string $note): Appointment
    {
        return DB::transaction(function () use ($changeRequest, $actor, $note) {
            $locked = $this->lockAppointment($changeRequest->appointment);
            $lockedRequest = $this->lockChangeRequest($changeRequest->id);

            if ($lockedRequest->type !== AppointmentChangeRequest::TYPE_RESCHEDULE) {
                throw new DomainException('Only reschedule requests can be approved here.');
            }

            if ($lockedRequest->status !== AppointmentChangeRequest::STATUS_PENDING) {
                throw new DomainException(sprintf(
                    'This change request can no longer be approved - its current status is "%s". The page has been refreshed with the latest state.',
                    $lockedRequest->status
                ));
            }

            $this->assertOnlineSource($locked);
            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'approve_reschedule');
            $this->assertNotScheduledStartPassed($locked);

            // K5: delivery approvals are ALWAYS date-only, whatever a
            // (possibly forged) request row carries in proposed_time.
            $time = $lockedRequest->proposed_time;

            if ($locked->appointment_type === Appointment::TYPE_DELIVERY_REQUEST) {
                $time = null;
            }

            $date = $lockedRequest->proposed_date?->toDateString();

            if ($date === null) {
                throw new DomainException('This reschedule request has no proposed date and cannot be approved.');
            }

            $time = $this->validateSchedule($locked->appointment_type, $date, $time);

            // The CURRENT schedule (the one the patient can see):
            // confirmed schedule for a confirmed row, requested schedule
            // as the defensive fallback - shared with guard 1 and the
            // audit's "old schedule".
            $currentDate = ($locked->confirmed_date ?? $locked->requested_date)?->toDateString();
            $currentTime = $locked->confirmed_date !== null
                ? ($locked->confirmed_time !== null ? substr((string) $locked->confirmed_time, 0, 5) : null)
                : ($locked->requested_time !== null ? substr((string) $locked->requested_time, 0, 5) : null);

            if ($date === $currentDate && $time === $currentTime) {
                throw new DomainException(
                    'The proposed schedule is identical to the appointment\'s current confirmed schedule. Decline the request instead of approving it.'
                );
            }

            $this->assertNoConflictingActiveAppointment($locked, $date, $time);

            $note = $note !== null && trim($note) !== '' ? trim($note) : null;

            $locked->confirmed_date = $date;
            $locked->confirmed_time = $time;
            $locked->save();

            $lockedRequest->status = AppointmentChangeRequest::STATUS_APPROVED;
            $lockedRequest->reviewed_by = $actor->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->review_note = $note;
            $lockedRequest->save();

            $this->audit($actor, $locked, 'RESCHEDULE_REQUEST_APPROVED', Appointment::STATUS_CONFIRMED, Appointment::STATUS_CONFIRMED, sprintf(
                'Patient reschedule request #%d approved by user ID %d: appointment #%d for patient ID %d moved from %s %s to %s %s. Staff note: %s',
                $lockedRequest->id,
                $actor->id,
                $locked->id,
                $locked->patient_id,
                $currentDate ?? '(none)',
                $currentTime ?? '(date only)',
                $date,
                $time ?? '(date only)',
                $note ?? '(none)'
            ));

            return $locked;
        });
    }

    /**
     * Phase 3D.3B Step 2 - Admin/Staff DECLINE of a pending patient
     * reschedule request: the appointment stays CONFIRMED with its
     * ORIGINAL confirmed schedule fully intact (a decline changes
     * nothing about the appointment row), and the REQUIRED explanation
     * (3-1000) is stored on the REQUEST row's review_note for the
     * portal card to display.
     *
     * Same lock-first ordering and in-transaction revalidation as
     * approve (type=reschedule + still pending; appointment still
     * confirmed + online source). Deliberately NO scheduled-start cutoff
     * and NO proposed-schedule validation here: declining mutates
     * nothing on the appointment, so an EXPIRED proposal (or one whose
     * appointment has already started) can always be declined as
     * cleanup - refusing would strand it as pending forever (approved
     * rule: expired proposals cannot be APPROVED but may be declined).
     *
     * @throws DomainException on stale/invalid state
     */
    public function declineRescheduleRequest(AppointmentChangeRequest $changeRequest, User $actor, string $note): AppointmentChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $actor, $note) {
            $locked = $this->lockAppointment($changeRequest->appointment);
            $lockedRequest = $this->lockChangeRequest($changeRequest->id);

            if ($lockedRequest->type !== AppointmentChangeRequest::TYPE_RESCHEDULE) {
                throw new DomainException('Only reschedule requests can be declined here.');
            }

            if ($lockedRequest->status !== AppointmentChangeRequest::STATUS_PENDING) {
                throw new DomainException(sprintf(
                    'This change request can no longer be reviewed - its current status is "%s". The page has been refreshed with the latest state.',
                    $lockedRequest->status
                ));
            }

            $this->assertOnlineSource($locked);
            $this->assertStatus($locked, Appointment::STATUS_CONFIRMED, 'decline_reschedule');

            $note = $this->normalizeReason($note);

            $lockedRequest->status = AppointmentChangeRequest::STATUS_DECLINED;
            $lockedRequest->reviewed_by = $actor->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->review_note = $note;
            $lockedRequest->save();

            $this->audit($actor, $locked, 'RESCHEDULE_REQUEST_DECLINED', Appointment::STATUS_CONFIRMED, Appointment::STATUS_CONFIRMED, sprintf(
                'Patient reschedule request #%d declined by user ID %d: appointment #%d for patient ID %d keeps its confirmed schedule. Explanation: %s',
                $lockedRequest->id,
                $actor->id,
                $locked->id,
                $locked->patient_id,
                $note
            ));

            return $lockedRequest;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Re-read the row under the transaction's lock (TOCTOU protection). */
    private function lockAppointment(Appointment $appointment): Appointment
    {
        $locked = Appointment::whereKey($appointment->id)->lockForUpdate()->first();

        if (! $locked) {
            throw new DomainException('This appointment record no longer exists.');
        }

        return $locked;
    }

    /**
     * Phase 3D.2C - re-read a change request under the transaction's lock.
     * ALWAYS called AFTER lockAppointment() (never before), so every
     * approve/decline/auto-resolution path locks rows in the same order
     * (appointment first, request second) and cannot deadlock against
     * requestAppointmentCancellation's one-pending check.
     */
    private function lockChangeRequest(int $requestId): AppointmentChangeRequest
    {
        $locked = AppointmentChangeRequest::whereKey($requestId)->lockForUpdate()->first();

        if (! $locked) {
            throw new DomainException('This change request no longer exists.');
        }

        return $locked;
    }

    /**
     * Phase 3D.2C - change requests exist only for ONLINE appointments
     * (walk-ins keep the plain staff workflow). Structural re-check at
     * the write boundary: even a directly-inserted request row against a
     * walk-in appointment can never cancel or annotate it through the
     * review path.
     *
     * @throws DomainException when the appointment is not online-sourced
     */
    private function assertOnlineSource(Appointment $appointment): void
    {
        if ($appointment->source !== Appointment::SOURCE_ONLINE) {
            throw new DomainException('Only appointments booked online can be reviewed here. This appointment was not booked online.');
        }
    }

    /**
     * Phase 3D.3B Step 1 - collision guard 2 (approved decision K2): the
     * proposed schedule must not EXACTLY match the effective schedule of
     * ANOTHER active (pending or confirmed) appointment of the same
     * patient and type. The appointment being rescheduled is excluded,
     * so its own current schedule never blocks the proposal (guard 1
     * owns that case separately).
     *
     * Effective schedule of the other row: its CONFIRMED schedule when
     * it is confirmed with a confirmed date (the schedule the patient
     * will actually experience), otherwise its REQUESTED schedule (a
     * pending row, or a defensive confirmed-row fallback). Exact match
     * means same date AND same time - a date-only proposal (null time)
     * matches only another date-only row, per the "exact duplicate"
     * wording of the approved rule.
     *
     * Comparison runs in PHP over the patient's same-type active rows
     * (clinic-scale volume, and portable across SQLite and MySQL - the
     * confirmed/requested fallback cannot be expressed as a single
     * indexed WHERE). This is a read-before-insert check inside the
     * transaction under the appointment lock, NOT a database
     * constraint (same honest limitation as createOnlineRequest's
     * duplicate policy).
     *
     * @throws DomainException when another active appointment holds the
     *                         exact proposed schedule
     */
    private function assertNoConflictingActiveAppointment(Appointment $appointment, string $date, ?string $time): void
    {
        $others = Appointment::query()
            ->where('patient_id', $appointment->patient_id)
            ->where('appointment_type', $appointment->appointment_type)
            ->whereIn('status', [
                Appointment::STATUS_PENDING,
                Appointment::STATUS_CONFIRMED,
            ])
            ->whereKeyNot($appointment->id)
            ->get();

        foreach ($others as $other) {
            $otherDate = $other->status === Appointment::STATUS_CONFIRMED && $other->confirmed_date !== null
                ? $other->confirmed_date->toDateString()
                : $other->requested_date?->toDateString();

            $otherTime = $other->status === Appointment::STATUS_CONFIRMED && $other->confirmed_date !== null
                ? ($other->confirmed_time !== null ? substr((string) $other->confirmed_time, 0, 5) : null)
                : ($other->requested_time !== null ? substr((string) $other->requested_time, 0, 5) : null);

            if ($otherDate === $date && $otherTime === $time) {
                throw new DomainException(
                    'You already have an active appointment for this exact schedule. Please choose a different date or time.'
                );
            }
        }
    }

    /**
     * Phase 3D.2C - close every still-pending change request for this
     * appointment inside the CALLER's transaction (called from cancel()
     * and complete() after the appointment mutation and its audit row).
     *
     * Auto-resolution matrix (user-approved Phase 3D.2A decision):
     *   - pending cancellation + staff cancel  -> auto-approved
     *   - pending cancellation + staff complete -> auto-declined
     *   - pending reschedule + either action   -> auto-declined
     * reviewed_by/reviewed_at/review_note are stamped exactly like a
     * manual review (the note records WHY it was automatic), and one
     * extra audit row per request documents the automatic decision
     * without altering the pre-existing staff action audit row.
     *
     * NO patient notification is sent from here by design: the patient
     * already receives the appointment's own status notification (Phase
     * 3B.4) for the staff cancel/complete that caused the auto-close, so
     * a second notification would be a duplicate of the same event.
     */
    private function autoResolvePendingChangeRequests(Appointment $appointment, User $actor, string $staffAction): void
    {
        $pendingRequests = AppointmentChangeRequest::query()
            ->where('appointment_id', $appointment->id)
            ->where('status', AppointmentChangeRequest::STATUS_PENDING)
            ->lockForUpdate()
            ->get();

        foreach ($pendingRequests as $request) {
            $approved = $request->type === AppointmentChangeRequest::TYPE_CANCELLATION
                && $staffAction === 'cancelled';

            $previousRequestStatus = $request->status;

            $request->status = $approved
                ? AppointmentChangeRequest::STATUS_APPROVED
                : AppointmentChangeRequest::STATUS_DECLINED;
            $request->reviewed_by = $actor->id;
            $request->reviewed_at = now();
            $request->review_note = $approved
                ? 'Approved automatically: the appointment was cancelled directly by the clinic.'
                : sprintf('Declined automatically: the appointment was %s directly by the clinic.', $staffAction);
            $request->save();

            $this->audit($actor, $appointment, $approved
                ? 'CANCELLATION_REQUEST_AUTO_APPROVED'
                : 'CHANGE_REQUEST_AUTO_DECLINED', $previousRequestStatus, $request->status, sprintf(
                    'Change request #%d (%s) auto-%s because appointment #%d was %s directly by user ID %d.',
                    $request->id,
                    $request->type,
                    $approved ? 'approved' : 'declined',
                    $appointment->id,
                    $staffAction,
                    $actor->id
                ));
        }
    }

    /** Refuse any transition the workflow does not permit (incl. stale repeats). */
    private function assertStatus(Appointment $appointment, string $expected, string $action): void
    {
        if ($appointment->status !== $expected) {
            $past = match ($action) {
                'confirm' => 'confirmed',
                'decline' => 'declined',
                'cancel' => 'cancelled',
                'complete' => 'completed',
                'approve_cancellation' => 'cancelled through this cancellation request',
                'decline_cancellation' => 'left confirmed for this cancellation review',
                'approve_reschedule' => 'rescheduled through this reschedule request',
                'decline_reschedule' => 'left confirmed for this reschedule review',
                default => $action,
            };

            throw new DomainException(sprintf(
                'This appointment can no longer be %s - its current status is "%s". The page has been refreshed with the latest state.',
                $past,
                $appointment->status
            ));
        }
    }

    /**
     * Provisional schedule validation (see class docblock). Returns the
     * normalized H:i time, or null for an intentionally date-only
     * delivery request.
     *
     * @throws DomainException with a staff-facing message
     */
    private function validateSchedule(string $appointmentType, string $date, ?string $time): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new DomainException('The appointment date is not a valid calendar date.');
        }

        if ($appointmentType === Appointment::TYPE_DELIVERY_REQUEST) {
            // 24/7; time optional (date-only is a real, displayable state).
            $time = $this->normalizeTime($time);

            $this->assertNotPastSchedule($date, $time);

            return $time;
        }

        // checkup + counseling (and any future non-delivery type): a time
        // is required and must fall inside the provisional clinic hours.
        $time = $this->normalizeTime($time);

        if ($time === null) {
            throw new DomainException('An appointment time is required for checkup and counseling appointments.');
        }

        if ($time < self::CLINIC_OPEN_TIME || $time >= self::CLINIC_LAST_START_TIME) {
            throw new DomainException(sprintf(
                'Checkup and counseling appointments must start between %s and before %s (Philippine clinic hours, provisional).',
                self::CLINIC_OPEN_TIME,
                self::CLINIC_LAST_START_TIME
            ));
        }

        $this->assertNotPastSchedule($date, $time);

        return $time;
    }

    /**
     * Shared past-schedule rule (single source of truth, used by this
     * service AND both FormRequests for defence in depth).
     *
     * Full local date+time is compared against the current Asia/Manila
     * time (never date and time separately): a date before today is
     * always past; today requires a time at or after the current minute
     * (equal minute allowed); later dates pass; a date-only schedule is
     * allowed today or later. Returns null when acceptable, or
     * ['scope' => 'date'|'time', 'message' => ...] so callers can attach
     * the error to the correct form field. Invalid time formats are
     * ignored here - format rules own that error.
     *
     * @return array{scope: 'date'|'time', message: string}|null
     */
    public static function pastScheduleIssue(string $date, ?string $time): ?array
    {
        $now = Carbon::now(self::CLINIC_TIMEZONE);
        $time = $time !== null && trim($time) !== '' ? trim($time) : null;

        if ($time !== null && preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', $time, $parts) !== 1) {
            return null; // format rules report invalid times.
        }

        $normalizedTime = $time !== null ? $parts[1] : null;
        $scheduleDate = Carbon::createFromFormat('Y-m-d', $date, self::CLINIC_TIMEZONE);

        if (! $scheduleDate instanceof Carbon) {
            return null; // format rules report invalid dates.
        }

        // startOfDay: createFromFormat inherits the current time-of-day
        // for missing parts - without this, "today" would compare as a
        // later hour and skip the time check.
        $scheduleDate = $scheduleDate->startOfDay();
        $today = $now->copy()->startOfDay();

        if ($scheduleDate->lt($today)) {
            return [
                'scope' => 'date',
                'message' => sprintf(
                    'The appointment date (%s) is in the past. Today is %s (Philippine time). Choose today or a future date.',
                    $date,
                    $today->toDateString()
                ),
            ];
        }

        if ($scheduleDate->gt($today) || $normalizedTime === null) {
            return null; // future date, or date-only today.
        }

        $schedule = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $normalizedTime, self::CLINIC_TIMEZONE);

        if ($schedule instanceof Carbon && $schedule->lt($now->copy()->startOfMinute())) {
            return [
                'scope' => 'time',
                'message' => sprintf(
                    'The appointment time (%s %s, Philippine time) has already passed. Choose %s or a later time. Current Philippine time: %s %s.',
                    $date,
                    $normalizedTime,
                    $now->toDateString(),
                    $now->toDateString(),
                    $now->format('H:i')
                ),
            ];
        }

        return null;
    }

    /**
     * Service-level enforcement at the write boundary (inside the
     * transaction, so direct service calls cannot bypass the rule and a
     * stale form cannot slip past a midnight crossing).
     *
     * @throws DomainException with a staff-facing message
     */
    private function assertNotPastSchedule(string $date, ?string $time): void
    {
        $issue = self::pastScheduleIssue($date, $time);

        if ($issue !== null) {
            throw new DomainException($issue['message']);
        }
    }

    /**
     * Phase 3D.2B - the patient's OWNED appointment must be an ONLINE
     * source row. Re-checked inside the transaction under lock so a
     * direct service call (or a stale browser) can never mutate a
     * staff-managed walk-in through the patient path. Walk-ins keep the
     * existing Admin/Staff workflow untouched.
     *
     * @throws DomainException when ownership or source does not hold
     */
    private function assertPatientOwnedOnline(Appointment $appointment, Patient $patient): void
    {
        if ($appointment->patient_id !== $patient->id) {
            throw new DomainException('This appointment record no longer exists.');
        }

        if ($appointment->source !== Appointment::SOURCE_ONLINE) {
            throw new DomainException('Only appointments booked online can be managed here. Please contact the clinic about walk-in visits.');
        }
    }

    /**
     * Phase 3D.2B - patient cancellation cutoff: requests are accepted
     * only BEFORE the appointment's scheduled start (Manila). The
     * schedule used is the one the patient can currently see: confirmed
     * schedule for confirmed rows, requested schedule for pending rows.
     *
     * @throws DomainException once the scheduled start has passed
     */
    private function assertNotScheduledStartPassed(Appointment $appointment): void
    {
        $date = ($appointment->confirmed_date ?? $appointment->requested_date)?->toDateString();
        $time = $appointment->status === Appointment::STATUS_CONFIRMED
            ? ($appointment->confirmed_time ?? $appointment->requested_time)
            : $appointment->requested_time;

        $issue = self::scheduledStartPassedIssue($date, $time);

        if ($issue !== null) {
            throw new DomainException($issue);
        }
    }

    /**
     * Phase 3D.2B cutoff rule (user-approved interpretation, symmetric
     * with pastScheduleIssue's Manila anchoring):
     *   - date + time: allowed STRICTLY BEFORE the start minute; the
     *     start minute itself and every later minute are refused.
     *   - date-only (delivery): allowed through the END of the scheduled
     *     Manila calendar day; refused from the next day onward.
     * Comparisons use the current Asia/Manila time re-read at call time
     * (inside the transaction for service callers). Returns null when
     * acceptable, or a message when the cutoff has passed. A missing
     * date (no schedule recorded) enforces no cutoff - format rules own
     * invalid inputs.
     */
    public static function scheduledStartPassedIssue(?string $date, ?string $time): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $now = Carbon::now(self::CLINIC_TIMEZONE);
        $time = $time !== null && trim($time) !== '' ? trim($time) : null;

        if ($time !== null && preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', $time, $parts) === 1) {
            $normalizedTime = $parts[1];
        } else {
            $normalizedTime = null; // date-only (invalid formats are owned by format rules).
        }

        $scheduleDate = Carbon::createFromFormat('Y-m-d', $date, self::CLINIC_TIMEZONE);

        if (! $scheduleDate instanceof Carbon) {
            return null;
        }

        $scheduleDate = $scheduleDate->startOfDay();

        if ($normalizedTime === null) {
            // date-only: cancellable through the end of the scheduled day.
            if ($scheduleDate->lt($now->copy()->startOfDay())) {
                return                 sprintf(
                    'This appointment\'s scheduled date (%s) has already passed (Philippine time), so it can no longer be cancelled. Today is %s.',
                    $date,
                    $now->toDateString()
                );
            }

            return null;
        }

        $schedule = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $normalizedTime, self::CLINIC_TIMEZONE);

        if ($schedule instanceof Carbon && $schedule->lte($now->copy()->startOfMinute())) {
            return sprintf(
                'This appointment\'s scheduled start (%s %s, Philippine time) has already passed, so it can no longer be cancelled. Current Philippine time: %s %s.',
                $date,
                $normalizedTime,
                $now->toDateString(),
                $now->format('H:i')
            );
        }

        return null;
    }

    /** Accept H:i or H:i:s input, return canonical H:i, or null when absent. */
    private function normalizeTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $time = trim($time);

        if (preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', $time, $parts) !== 1) {
            throw new DomainException('The appointment time is not a valid time.');
        }

        return $parts[1];
    }

    private function normalizeReason(string $reason): string
    {
        $reason = trim($reason);

        Validator::make(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'min:3', 'max:1000']]
        )->validate();

        return $reason;
    }

    /**
     * One audit row per action, INSIDE the transaction (an action can never
     * exist without its audit record). Follows the established convention:
     * UPPER_SNAKE action, module label, actor user_id, patient_id, free-text
     * description. Never records a password, hash or session credential.
     */
    private function audit(User $actor, Appointment $appointment, string $action, ?string $previousStatus, string $newStatus, string $description): void
    {
        $statusPart = $previousStatus !== null
            ? sprintf(' Status: %s -> %s.', $previousStatus, $newStatus)
            : sprintf(' Status: %s.', $newStatus);

        AuditLog::create([
            'user_id' => $actor->id,
            'patient_id' => $appointment->patient_id,
            'action' => $action,
            'module' => 'APPOINTMENT',
            'description' => $description . $statusPart,
        ]);
    }
}
