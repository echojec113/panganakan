<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
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
     * Send at most once per logical event identity.
     *
     * The identity is stored as `event_key` inside the persisted payload and
     * compared exactly (decoded in PHP) against every existing row of the
     * same notification type, so the check is DB-portable and cannot be
     * fooled by partial matches. Row volume is inherently bounded: one row
     * per event (patient/pregnancy/assessment) per recipient.
     *
     * @param  class-string  $notificationType
     * @param  \Closure(): object  $makeNotification
     */
    private function sendOnce(string $notificationType, string $eventKey, \Closure $makeNotification): bool
    {
        if ($this->alreadySent($notificationType, $eventKey)) {
            return false;
        }

        Notification::send($this->clinicRecipients(), $makeNotification());

        return true;
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
