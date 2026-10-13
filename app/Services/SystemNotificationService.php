<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentCancellationApprovedNotification;
use App\Notifications\AppointmentCancellationDeclinedNotification;
use App\Notifications\AppointmentCancellationRequestedNotification;
use App\Notifications\AppointmentCompletedNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentDeclinedNotification;
use App\Notifications\AppointmentPatientCancelledNotification;
use App\Notifications\AppointmentRequestedNotification;
use App\Notifications\AppointmentRescheduleApprovedNotification;
use App\Notifications\AppointmentRescheduleDeclinedNotification;
use App\Notifications\AppointmentRescheduleRequestedNotification;
use App\Notifications\EddApproachingNotification;
use App\Notifications\PendingRepeatBloodPressureNotification;
use App\Notifications\PastEddNeedsReviewNotification;
use App\Notifications\PatientBecameHighRiskNotification;
use App\Notifications\ReferralClosedNotification;
use App\Notifications\ReferralCreatedNotification;
use App\Notifications\UrgentBloodPressureNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Single entry point for generating in-app notifications from real system
 * events. Every method is called only AFTER an actual record/state transition
 * has been persisted (never from a page render), so notifications are never
 * re-created just because a dashboard loaded.
 *
 * Recipient model: DEPLA is a single-clinic system - staff may open any
 * patient of the clinic, not only the ones they personally encoded. Every
 * notification in this service therefore goes to ALL active clinic users
 * (admin + staff) instead of depending on patients.assigned_staff_id.
 * Archived (soft-deleted) users are excluded automatically by the User
 * model's SoftDeletes global scope, so they never receive NEW alerts.
 *
 * Duplicate prevention has two independent layers:
 *  1. State-transition guards (e.g. the previous effective risk must have
 *     been non-HIGH) evaluated by the caller or in notifyHighRiskTransition().
 *  2. A stable `event_key` stored inside the notification payload, checked
 *     against the notifications ledger before any time-based alert is sent,
 *     which keeps scheduled runs idempotent.
 */
class SystemNotificationService
{
    /**
     * Recipients for every clinic-wide clinical and referral notification:
     * ALL active (non-archived) admin accounts plus ALL active staff
     * accounts, deduplicated by user id.
     *
     * @return Collection<int, User>
     */
    private function clinicRecipients(): Collection
    {
        return User::whereIn('role', ['admin', 'staff'])
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * The patient's EFFECTIVE (current) risk classification: the risk level of
     * the same latest non-deleted assessment the rest of the system uses
     * (Patient::latestPrenatalAssessment - newest clinical date, id tie-break).
     *
     * Always queries fresh so a snapshot taken before a write can never be
     * poisoned by an already-loaded relation on a shared model instance.
     */
    public function effectiveRiskLevel(int $patientId): ?string
    {
        $patient = Patient::query()->find($patientId);

        if (! $patient) {
            return null;
        }

        return $patient->latestPrenatalAssessment?->risk_level;
    }

    /**
     * The patient's latest prenatal visit carried an urgent BP finding
     * (BP-URG) and the urgency transitioned into URGENT_CLINICAL_REVIEW.
     */
    public function notifyUrgentBloodPressure(PrenatalVisit $visit): void
    {
        Notification::send(
            $this->clinicRecipients(),
            new UrgentBloodPressureNotification($visit)
        );
    }

    /**
     * The patient's latest visit transitioned to a PENDING_REPEAT BP
     * verification state and still requires a repeat measurement.
     */
    public function notifyPendingRepeatBloodPressure(PrenatalVisit $visit): void
    {
        Notification::send(
            $this->clinicRecipients(),
            new PendingRepeatBloodPressureNotification($visit)
        );
    }

    /**
     * Detect a NON-HIGH -> HIGH transition of the patient's EFFECTIVE risk
     * classification after the write has been persisted, and notify once.
     *
     * Callers snapshot the previous effective risk BEFORE mutating a
     * prenatal assessment (new visit, visit update, or ASSESSMENT INCOMPLETE
     * recalculation) and hand it here afterwards.
     *
     * HIGH -> HIGH never notifies: the guard returns early on the first
     * comparison, so an edit/recalculation of an already-HIGH patient cannot
     * produce a second alert. The event_key additionally pins the alert to
     * the exact assessment that caused the transition, so the same transition
     * can never be recorded twice while a later, genuine re-transition (a new
     * assessment) still alerts.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyHighRiskTransition(int $patientId, ?string $previousEffectiveRisk): bool
    {
        if ($previousEffectiveRisk === 'HIGH') {
            return false;
        }

        $patient = Patient::query()->find($patientId);

        if (! $patient) {
            return false;
        }

        $current = $patient->latestPrenatalAssessment?->risk_level;

        if ($current !== 'HIGH') {
            return false;
        }

        $eventKey = 'became-high-risk:' . $patientId . ':' . $patient->latestPrenatalAssessment->id;

        return $this->sendOnce(
            PatientBecameHighRiskNotification::class,
            $eventKey,
            fn () => new PatientBecameHighRiskNotification($patient, $eventKey)
        );
    }

    /**
     * An ongoing pregnancy entered the EDD - 7 days .. EDD window.
     * Idempotent: one alert per pregnancy ever, no matter how often the
     * scheduler runs.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyEddApproaching(Patient $patient): bool
    {
        $eventKey = 'edd-approaching:' . $patient->id;

        return $this->sendOnce(
            EddApproachingNotification::class,
            $eventKey,
            fn () => new EddApproachingNotification($patient, $eventKey)
        );
    }

    /**
     * An ongoing pregnancy is past its EDD with no recorded completion
     * outcome - a REVIEW alert only. This method never writes clinical or
     * lifecycle state.
     * Idempotent: one alert per pregnancy ever.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyPastEddNeedsReview(Patient $patient): bool
    {
        $eventKey = 'past-edd:' . $patient->id;

        return $this->sendOnce(
            PastEddNeedsReviewNotification::class,
            $eventKey,
            fn () => new PastEddNeedsReviewNotification($patient, $eventKey)
        );
    }

    /**
     * A new pending referral was created for a patient.
     */
    public function notifyReferralCreated(Referral $referral): void
    {
        Notification::send(
            $this->clinicRecipients(),
            new ReferralCreatedNotification($referral)
        );
    }

    /**
     * A pending referral transitioned to a terminal status
     * (Completed / Refused / Cancelled).
     */
    public function notifyReferralClosed(Referral $referral): void
    {
        Notification::send(
            $this->clinicRecipients(),
            new ReferralClosedNotification($referral)
        );
    }

    /**
     * A new ONLINE appointment request was created and committed.
     *
     * Walk-in appointments never reach this method (their creation path
     * does not call it), and callers must only invoke it AFTER the
     * createOnlineRequest() transaction has committed - mirroring the
     * established send-after-commit convention used by the prenatal and
     * referral controllers. The event_key pins the notification to the
     * exact appointment, so a repeated or retried request can never
     * produce a second clinic alert for the same row.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentRequested(Appointment $appointment): bool
    {
        if ($appointment->source !== Appointment::SOURCE_ONLINE) {
            return false;
        }

        $eventKey = 'appointment-requested:' . $appointment->id;

        return $this->sendOnceTo(
            $this->clinicRecipients(),
            AppointmentRequestedNotification::class,
            $eventKey,
            fn () => new AppointmentRequestedNotification($appointment, $eventKey)
        );
    }

    /**
     * An appointment status transition committed: notify the patient's
     * linked, active Patient Portal account (single recipient).
     *
     * Supported transitions are exactly the four Admin/Staff actions from
     * Phase 3B.2B (confirmed / declined / cancelled / completed). A patient
     * with no linked portal account, an archived (soft-deleted) account or
     * a non-patient role resolves to no recipient and safely no-ops - no
     * account is ever created here. Callers must invoke this only AFTER
     * the AppointmentManagementService transaction has committed; the
     * status-transition guards in that service (plus the event_key ledger
     * here) prevent duplicate notifications for repeated or invalid
     * requests.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentStatusChanged(Appointment $appointment, string $newStatus): bool
    {
        $recipient = $this->activePortalUserFor($appointment->patient_id);

        if ($recipient === null) {
            return false;
        }

        $eventKey = 'appointment-' . $newStatus . ':' . $appointment->id;

        $plan = match ($newStatus) {
            Appointment::STATUS_CONFIRMED => [AppointmentConfirmedNotification::class, fn () => new AppointmentConfirmedNotification($appointment, $eventKey)],
            Appointment::STATUS_DECLINED => [AppointmentDeclinedNotification::class, fn () => new AppointmentDeclinedNotification($appointment, $eventKey)],
            Appointment::STATUS_CANCELLED => [AppointmentCancelledNotification::class, fn () => new AppointmentCancelledNotification($appointment, $eventKey)],
            Appointment::STATUS_COMPLETED => [AppointmentCompletedNotification::class, fn () => new AppointmentCompletedNotification($appointment, $eventKey)],
            default => null,
        };

        if ($plan === null) {
            return false;
        }

        [$notificationClass, $makeNotification] = $plan;

        return $this->sendOnceTo(
            collect([$recipient]),
            $notificationClass,
            $eventKey,
            $makeNotification
        );
    }

    /**
     * Phase 3D.2B - a PENDING ONLINE appointment was cancelled DIRECTLY
     * by its owning patient (no approval). Notify every active Admin/
     * Staff account after the cancelByPatient() transaction commits.
     *
     * Walk-in rows and non-online sources never reach this method (the
     * service refuses them on the patient path), and the patient is
     * deliberately NOT notified about their own action. The event_key
     * pins the alert to the exact appointment, so a repeated or retried
     * dispatch can never produce a second clinic alert.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyPatientCancelledPendingAppointment(Appointment $appointment): bool
    {
        if ($appointment->source !== Appointment::SOURCE_ONLINE) {
            return false;
        }

        $eventKey = 'appointment-patient-cancelled:' . $appointment->id;

        return $this->sendOnceTo(
            $this->clinicRecipients(),
            AppointmentPatientCancelledNotification::class,
            $eventKey,
            fn () => new AppointmentPatientCancelledNotification($appointment, $eventKey)
        );
    }

    /**
     * Phase 3D.2B - a PATIENT submitted a cancellation REQUEST against a
     * CONFIRMED online appointment. Notify every active Admin/Staff
     * account after the requestAppointmentCancellation() transaction
     * commits; the appointment itself stays confirmed until staff review
     * (a later step). The event_key pins the alert to the exact change
     * request row, so repeated dispatches are no-ops.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentCancellationRequested(AppointmentChangeRequest $request): bool
    {
        if ($request->type !== AppointmentChangeRequest::TYPE_CANCELLATION
            || $request->status !== AppointmentChangeRequest::STATUS_PENDING) {
            return false;
        }

        $eventKey = 'appointment-cancel-requested:' . $request->id;

        return $this->sendOnceTo(
            $this->clinicRecipients(),
            AppointmentCancellationRequestedNotification::class,
            $eventKey,
            fn () => new AppointmentCancellationRequestedNotification($request, $eventKey)
        );
    }

    /**
     * Phase 3D.2C - Admin/Staff APPROVED a patient cancellation request:
     * the confirmed online appointment is now cancelled. Notify the
     * patient's linked, active Patient Portal account after the
     * approveCancellationRequest() transaction commits.
     *
     * This is the SINGLE patient notification for the approval path -
     * the caller deliberately does NOT also call
     * notifyAppointmentStatusChanged, so the patient never receives two
     * messages for one approval. Auto-resolution caused by a staff
     * cancel/complete never reaches this method (it sends no extra
     * notification by design; the existing status notification for the
     * cancel/complete already covers the outcome). The event_key pins
     * the alert to the exact change request row, so repeated dispatches
     * are no-ops.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentCancellationApproved(AppointmentChangeRequest $request): bool
    {
        if ($request->type !== AppointmentChangeRequest::TYPE_CANCELLATION
            || $request->status !== AppointmentChangeRequest::STATUS_APPROVED) {
            return false;
        }

        $recipient = $this->activePortalUserFor($request->appointment->patient_id);

        if ($recipient === null) {
            return false;
        }

        $eventKey = 'appointment-cancellation-approved:' . $request->id;

        return $this->sendOnceTo(
            collect([$recipient]),
            AppointmentCancellationApprovedNotification::class,
            $eventKey,
            fn () => new AppointmentCancellationApprovedNotification($request, $eventKey)
        );
    }

    /**
     * Phase 3D.2C - Admin/Staff DECLINED a patient cancellation request:
     * the confirmed appointment stays confirmed with its schedule intact.
     * Notify the patient's linked, active Patient Portal account after
     * the declineCancellationRequest() transaction commits.
     *
     * This is the SINGLE patient notification for the decline path (the
     * generic status-changed notification is deliberately NOT sent - the
     * appointment's status did not change). The decline explanation
     * stays out of the payload: it is displayed on the authorized My
     * Appointments card only. Auto-resolution never reaches this method.
     * The event_key pins the alert to the exact change request row, so
     * repeated dispatches are no-ops.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentCancellationDeclined(AppointmentChangeRequest $request): bool
    {
        if ($request->type !== AppointmentChangeRequest::TYPE_CANCELLATION
            || $request->status !== AppointmentChangeRequest::STATUS_DECLINED) {
            return false;
        }

        $recipient = $this->activePortalUserFor($request->appointment->patient_id);

        if ($recipient === null) {
            return false;
        }

        $eventKey = 'appointment-cancellation-declined:' . $request->id;

        return $this->sendOnceTo(
            collect([$recipient]),
            AppointmentCancellationDeclinedNotification::class,
            $eventKey,
            fn () => new AppointmentCancellationDeclinedNotification($request, $eventKey)
        );
    }

    /**
     * Phase 3D.3B Step 3 - a PATIENT submitted a reschedule REQUEST
     * against a CONFIRMED online appointment. Notify every active
     * Admin/Staff account after the requestAppointmentReschedule()
     * transaction commits; the confirmed schedule stays intact until
     * staff review. The event_key pins the alert to the exact change
     * request row, so repeated dispatches are no-ops (and a refused
     * submission - validation failure, cutoff, duplicate - never reaches
     * this method, so no notification exists for it).
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentRescheduleRequested(AppointmentChangeRequest $request): bool
    {
        if ($request->type !== AppointmentChangeRequest::TYPE_RESCHEDULE
            || $request->status !== AppointmentChangeRequest::STATUS_PENDING) {
            return false;
        }

        $eventKey = 'appointment-reschedule-requested:' . $request->id;

        return $this->sendOnceTo(
            $this->clinicRecipients(),
            AppointmentRescheduleRequestedNotification::class,
            $eventKey,
            fn () => new AppointmentRescheduleRequestedNotification($request, $eventKey)
        );
    }

    /**
     * Phase 3D.3B Step 3 - Admin/Staff APPROVED a patient reschedule
     * request: the appointment now follows the exact proposed schedule
     * (delivery requests land date-only). Notify the patient's linked,
     * active Patient Portal account after the approveRescheduleRequest()
     * transaction commits.
     *
     * This is the SINGLE patient notification for the approval path - the
     * caller deliberately does NOT also call notifyAppointmentStatusChanged
     * (the appointment's status never changed, only its schedule), so the
     * patient never receives two messages for one approval. Auto-resolution
     * (staff completion of the appointment) never reaches this method: it
     * produces no new notification, and the existing status notification
     * for the completion already covers the outcome. The event_key pins
     * the alert to the exact change request row, so repeated dispatches
     * are no-ops.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentRescheduleApproved(AppointmentChangeRequest $request): bool
    {
        if ($request->type !== AppointmentChangeRequest::TYPE_RESCHEDULE
            || $request->status !== AppointmentChangeRequest::STATUS_APPROVED) {
            return false;
        }

        $recipient = $this->activePortalUserFor($request->appointment->patient_id);

        if ($recipient === null) {
            return false;
        }

        $eventKey = 'appointment-reschedule-approved:' . $request->id;

        return $this->sendOnceTo(
            collect([$recipient]),
            AppointmentRescheduleApprovedNotification::class,
            $eventKey,
            fn () => new AppointmentRescheduleApprovedNotification($request, $eventKey)
        );
    }

    /**
     * Phase 3D.3B Step 3 - Admin/Staff DECLINED a patient reschedule
     * request: the confirmed appointment keeps its ORIGINAL confirmed
     * schedule. Notify the patient's linked, active Patient Portal
     * account after the declineRescheduleRequest() transaction commits.
     *
     * This is the SINGLE patient notification for the decline path (the
     * generic status-changed notification is deliberately NOT sent - the
     * appointment's status did not change). The decline explanation
     * stays out of the payload: it will be shown only on the patient's
     * appointment card in the later UI step. The event_key pins the
     * alert to the exact change request row, so repeated dispatches are
     * no-ops.
     *
     * @return bool True when a notification was actually sent.
     */
    public function notifyAppointmentRescheduleDeclined(AppointmentChangeRequest $request): bool
    {
        if ($request->type !== AppointmentChangeRequest::TYPE_RESCHEDULE
            || $request->status !== AppointmentChangeRequest::STATUS_DECLINED) {
            return false;
        }

        $recipient = $this->activePortalUserFor($request->appointment->patient_id);

        if ($recipient === null) {
            return false;
        }

        $eventKey = 'appointment-reschedule-declined:' . $request->id;

        return $this->sendOnceTo(
            collect([$recipient]),
            AppointmentRescheduleDeclinedNotification::class,
            $eventKey,
            fn () => new AppointmentRescheduleDeclinedNotification($request, $eventKey)
        );
    }

    /**
     * The patient's linked, ACTIVE Patient Portal login, or null.
     *
     * Resolves through patients.user_id -> users.id. The User model's
     * SoftDeletes global scope automatically excludes archived accounts,
     * and the role check refuses a mis-linked non-patient user, so an
     * unlinked or archived portal account can never receive a notification
     * (and no replacement account is ever created).
     */
    private function activePortalUserFor(int $patientId): ?User
    {
        $patient = Patient::query()->find($patientId);

        if ($patient === null || $patient->user_id === null) {
            return null;
        }

        return User::query()
            ->where('id', $patient->user_id)
            ->where('role', 'patient')
            ->first();
    }

    /**
     * Send at most once per logical event identity to an arbitrary set of
     * recipients (clinic-wide or a single patient portal account).
     *
     * The identity is stored as `event_key` inside the persisted payload and
     * compared exactly (decoded in PHP) against every existing row of the
     * same notification type, so the check is DB-portable and cannot be
     * fooled by partial matches. Row volume is inherently bounded: one row
     * per event (patient/pregnancy/assessment/appointment) per recipient.
     *
     * @param  Collection<int, User>  $recipients
     * @param  class-string  $notificationType
     * @param  \Closure(): object  $makeNotification
     */
    private function sendOnceTo(Collection $recipients, string $notificationType, string $eventKey, \Closure $makeNotification): bool
    {
        if ($recipients->isEmpty() || $this->alreadySent($notificationType, $eventKey)) {
            return false;
        }

        Notification::send($recipients, $makeNotification());

        return true;
    }

    /**
     * Send at most once per logical event identity to the clinic-wide
     * recipient set (admin + staff).
     *
     * @param  class-string  $notificationType
     * @param  \Closure(): object  $makeNotification
     */
    private function sendOnce(string $notificationType, string $eventKey, \Closure $makeNotification): bool
    {
        return $this->sendOnceTo($this->clinicRecipients(), $notificationType, $eventKey, $makeNotification);
    }

    /**
     * @param  class-string  $notificationType
     */
    private function alreadySent(string $notificationType, string $eventKey): bool
    {
        return DB::table('notifications')
            ->where('type', $notificationType)
            ->pluck('data')
            ->contains(function ($data) use ($eventKey) {
                $decoded = json_decode((string) $data, true);

                return is_array($decoded) && ($decoded['event_key'] ?? null) === $eventKey;
            });
    }
}
