<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\PrenatalVisit;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * DEPLA Phase 3 - link a prenatal visit's Next Visit date + time to a
 * staff-created CONFIRMED follow-up appointment in the shared appointments
 * calendar (so the slot is actually held and the patient sees it in My
 * Appointments).
 *
 * SCOPE AND CONTRACT
 * - Staff-managed only. Nothing in this class is reachable from the patient
 *   portal: PatientAppointmentController's service re-asserts
 *   source === online on every cancel/cancellation-request/reschedule
 *   request, so a staff_followup row can never be mutated by a patient.
 * - A suggestion is NEVER an appointment. The caller passes the FINAL
 *   schedule (the staff-entered value, or null when the field was left
 *   empty and only the risk engine's suggested date applies). Appointment
 *   creation requires BOTH a date and a time, so the risk-generated
 *   date-only suggestion (HIGH = 3 days, LOW = 30 days) can never book a
 *   slot. No default time is ever invented anywhere in this class.
 * - Exactly one live appointment per prenatal visit: the visit row is
 *   lockForUpdate'd first, the linked appointment second (a fixed order
 *   used by every caller), so two concurrent saves of the same visit
 *   serialize instead of producing two holds.
 * - The UNIQUE (hold_date, slot_start) index on appointment_slot_holds is
 *   the authoritative double-booking barrier. Every schedule validation in
 *   here is a read-side pre-check for a friendly message; the hold INSERT
 *   or UPDATE is always the LAST write of the transaction, so a lost race
 *   surfaces as the already-translated DomainException (never an HTTP 500)
 *   and the whole transaction rolls back - no orphan appointment, hold or
 *   audit row survives a failure.
 *
 * CLEARING (user-approved decision 2): when the Next Visit schedule is
 * removed, or reduced to a date-only value, any linked LIVE appointment is
 * cancelled through the existing AppointmentManagementService (which
 * releases the slot hold, keeps the row as history and writes the standard
 * APPOINTMENT_CANCELLED audit). The controller only reaches this path after
 * the staff ticked the explicit "Clear Scheduled Next Visit" confirmation,
 * so a schedule can never disappear by accident.
 *
 * REMINDER OWNERSHIP: this class sends NO reminder of its own. The
 * appointment workflow produces database-only notifications, and
 * app:send-prenatal-reminders remains the single owner of next-visit
 * email reminders (keyed on prenatal_visits.next_visit_date), so a linked
 * follow-up never produces two emails for one visit. No new scheduler is
 * introduced.
 */
class PrenatalFollowUpAppointmentService
{
    /** Stored on a follow-up cancelled by clearing the Next Visit schedule. */
    public const CLEAR_REASON = 'Follow-up appointment cleared from the prenatal visit Next Visit schedule.';

    /** Audit action when a follow-up appointment is first created. */
    public const AUDIT_CREATED = 'PRENATAL_FOLLOWUP_APPOINTMENT_CREATED';

    /** Audit action when an existing follow-up moves to a new schedule. */
    public const AUDIT_RESCHEDULED = 'PRENATAL_FOLLOWUP_APPOINTMENT_RESCHEDULED';

    public function __construct(
        private readonly ClinicAvailabilityService $availability,
        private readonly AppointmentManagementService $appointments,
    ) {
    }

    /**
     * Reconcile the visit's Next Visit schedule with its linked appointment.
     *
     * @param  string|null  $date  Y-m-d of the FINAL next visit, or null
     * @param  string|null  $time  H:i of the FINAL next visit, or null
     *
     * @return array{appointment: Appointment|null, event: string}
     *         event is one of created | rescheduled | unchanged | cleared | none
     *
     * @throws DomainException when the schedule is invalid, in the past,
     *                         closed, or the slot was just taken
     */
    public function sync(PrenatalVisit $visit, ?string $date, ?string $time, User $actor): array
    {
        $date = $this->normalizeDate($date);
        $time = $this->normalizeTime($time);

        if ($date === null || $time === null) {
            return $this->clear($visit, $actor);
        }

        return DB::transaction(function () use ($visit, $date, $time, $actor) {
            $lockedVisit = $this->lockVisit($visit);
            $linked = $this->lockLinkedAppointment($lockedVisit);

            $time = $this->validateSchedule($date, $time, $linked?->id);

            if ($linked === null) {
                return [
                    'appointment' => $this->createFollowUp($lockedVisit, $date, $time, $actor),
                    'event' => 'created',
                ];
            }

            $sameSchedule = $linked->confirmed_date?->toDateString() === $date
                && $this->normalizeTime((string) $linked->confirmed_time) === $time
                && $linked->requested_date?->toDateString() === $date
                && $this->normalizeTime((string) $linked->requested_time) === $time;

            if ($sameSchedule) {
                return ['appointment' => $linked, 'event' => 'unchanged'];
            }

            return [
                'appointment' => $this->rescheduleFollowUp($linked, $date, $time, $actor),
                'event' => 'rescheduled',
            ];
        });
    }

    /**
     * Cancel the visit's LIVE linked appointment (if any) and release its
     * slot hold, keeping the row as history. Safe to call when nothing is
     * linked - the no-op case simply returns null.
     *
     * The caller (controller) is responsible for requiring the explicit
     * staff confirmation before reaching this method.
     *
     * @return array{appointment: Appointment|null, event: string}
     */
    public function clear(PrenatalVisit $visit, User $actor): array
    {
        return DB::transaction(function () use ($visit, $actor) {
            $lockedVisit = $this->lockVisit($visit);
            $linked = $this->lockLinkedAppointment($lockedVisit);

            if ($linked === null) {
                return ['appointment' => null, 'event' => 'none'];
            }

            // Re-checked under the lock: only the two states that still
            // occupy operational capacity are cleared. Completed, already
            // cancelled and declined rows are history and are never
            // rewritten (they hold no slot anyway).
            if ($linked->status === Appointment::STATUS_CONFIRMED) {
                $cancelled = $this->appointments->cancel($linked, $actor, self::CLEAR_REASON);

                return ['appointment' => $cancelled, 'event' => 'cleared'];
            }

            if ($linked->status === Appointment::STATUS_PENDING) {
                $declined = $this->appointments->decline($linked, $actor, self::CLEAR_REASON);

                return ['appointment' => $declined, 'event' => 'cleared'];
            }

            return ['appointment' => null, 'event' => 'none'];
        });
    }

    /**
     * Read-only pre-check so the controller can report a field error BEFORE
     * the visit transaction starts (no wasted work, no half-saved visit).
     * The authoritative re-check still happens inside sync()'s transaction.
     *
     * @throws DomainException
     */
    public function assertScheduleUsable(?string $date, ?string $time, ?int $ignoreAppointmentId = null): void
    {
        $date = $this->normalizeDate($date);
        $time = $this->normalizeTime($time);

        if ($date === null || $time === null) {
            return;
        }

        $this->validateSchedule($date, $time, $ignoreAppointmentId);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function lockVisit(PrenatalVisit $visit): PrenatalVisit
    {
        return PrenatalVisit::query()
            ->whereKey($visit->id)
            ->lockForUpdate()
            ->first() ?? $visit;
    }

    /**
     * The visit's LIVE linked appointment, re-read under lockForUpdate so
     * a concurrent save of the same visit serializes on this row too.
     */
    private function lockLinkedAppointment(PrenatalVisit $visit): ?Appointment
    {
        $active = $visit->activeFollowUpAppointment();

        if ($active === null) {
            return null;
        }

        $locked = Appointment::query()
            ->whereKey($active->id)
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            return null;
        }

        if (! in_array($locked->status, [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED], true)) {
            return null;
        }

        if ((int) $locked->patient_id !== (int) $visit->patient_id) {
            throw new DomainException(
                'The follow-up appointment linked to this prenatal visit belongs to a different patient record. Please contact the system administrator before saving.'
            );
        }

        if ((int) $locked->prenatal_visit_id !== (int) $visit->id) {
            throw new DomainException(
                'The follow-up appointment linked to this prenatal visit is inconsistent. Please contact the system administrator before saving.'
            );
        }

        return $locked;
    }

    /**
     * Authoritative schedule validation. Runs INSIDE the transaction, after
     * the locks, immediately before the write.
     *
     * @param  int|null  $ignoreAppointmentId  the appointment being edited,
     *                                         so its own slot stays selectable
     *
     * @throws DomainException
     */
    private function validateSchedule(string $date, string $time, ?int $ignoreAppointmentId): string
    {
        $this->availability->assertSchedulingSchemaReady();

        $date = $this->availability->assertDate($date);
        $time = $this->availability->assertSlotStart($time);

        if ($this->availability->isDateFullyClosed($date)) {
            throw new DomainException(
                'The clinic is unavailable for appointments on the selected date. Please choose another available date.'
            );
        }

        if ($this->availability->isSlotClosed($date, $time)) {
            throw new DomainException(
                'The selected time is unavailable because the clinic is closed during that period. Please choose a different time.'
            );
        }

        if ($this->availability->isSlotPast($date, $time)) {
            throw new DomainException(
                'The selected follow-up time has already passed. Please choose an available future slot.'
            );
        }

        if ($this->availability->isSlotOccupied($date, $time, $ignoreAppointmentId)) {
            throw new DomainException(
                'That appointment time slot is no longer available. Please choose a different available time.'
            );
        }

        return $time;
    }

    /**
     * The single creation path. Note the write ORDER: appointment row
     * first, slot hold LAST - the UNIQUE (hold_date, slot_start) index is
     * the final barrier, so a concurrent winner gets a clean DomainException
     * and this transaction rolls back completely.
     *
     * @throws DomainException
     */
    private function createFollowUp(PrenatalVisit $visit, string $date, string $time, User $actor): Appointment
    {
        $appointment = new Appointment();
        $appointment->patient_id = $visit->patient_id;
        $appointment->prenatal_visit_id = $visit->id;
        $appointment->appointment_type = Appointment::TYPE_CHECKUP;
        $appointment->source = Appointment::SOURCE_STAFF_FOLLOWUP;
        $appointment->requested_date = $date;
        $appointment->requested_time = $time;
        $appointment->confirmed_date = $date;
        $appointment->confirmed_time = $time;
        $appointment->status = Appointment::STATUS_CONFIRMED;
        $appointment->reviewed_by = $actor->id;
        $appointment->reviewed_at = now();
        $appointment->save();

        $this->availability->reserveHold($appointment, $date, $time);

        $this->audit(
            $actor,
            $appointment,
            self::AUDIT_CREATED,
            sprintf(
                'Prenatal follow-up appointment #%d created for patient ID %d from prenatal visit ID %d (%s %s).',
                $appointment->id,
                $appointment->patient_id,
                $visit->id,
                $date,
                $time
            )
        );

        return $appointment;
    }

    /**
     * Move an EXISTING follow-up to the new schedule in place - one row,
     * one hold (moved, never duplicated), full history preserved.
     *
     * @throws DomainException
     */
    private function rescheduleFollowUp(Appointment $appointment, string $date, string $time, User $actor): Appointment
    {
        $previousDate = $appointment->confirmed_date?->toDateString();
        $previousTime = $this->normalizeTime((string) $appointment->confirmed_time);

        $appointment->requested_date = $date;
        $appointment->requested_time = $time;
        $appointment->confirmed_date = $date;
        $appointment->confirmed_time = $time;
        $appointment->save();

        if ($this->availability->hasHold($appointment)) {
            $this->availability->moveHold($appointment, $date, $time);
        } else {
            $this->availability->reserveHold($appointment, $date, $time);
        }

        $this->audit(
            $actor,
            $appointment,
            self::AUDIT_RESCHEDULED,
            sprintf(
                'Prenatal follow-up appointment #%d for patient ID %d moved from %s %s to %s %s.',
                $appointment->id,
                $appointment->patient_id,
                $previousDate ?? '(no date)',
                $previousTime ?? '(no time)',
                $date,
                $time
            )
        );

        return $appointment;
    }

    /** Same audit shape as AppointmentManagementService::audit(). */
    private function audit(User $actor, Appointment $appointment, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'patient_id' => $appointment->patient_id,
            'action' => $action,
            'module' => 'APPOINTMENT',
            'description' => $description . ' Status: ' . $appointment->status . '.',
        ]);
    }

    private function normalizeDate(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        return trim($date);
    }

    private function normalizeTime(?string $time): ?string
    {
        return self::canonicalTime($time);
    }

    /**
     * Canonical H:i form used EVERYWHERE a Next Visit / slot time is read
     * or compared.
     *
     * A MySQL `time` column hands back "H:i:s" while the form and the slot
     * grid use "H:i". Feeding the raw value back into the database or the
     * picker would make a perfectly unchanged schedule look different
     * (false "changed" detection, and a preselected slot that never
     * matches). Normalising on WRITE and on COMPARE keeps both sides in
     * one shape regardless of the driver.
     *
     * Anything unparseable becomes null - "no time chosen" - so a bad value
     * can never be mistaken for a bookable slot.
     */
    public static function canonicalTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $time = trim($time);

        if (preg_match('/^(\d{1,2}:\d{2})(:\d{2})?$/', $time, $parts) !== 1) {
            return null;
        }

        return sprintf('%02d:%s', (int) substr($parts[1], 0, 2), substr($parts[1], 3, 2));
    }
}
