<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\PendingRepeatBloodPressureNotification;
use App\Notifications\ReferralClosedNotification;
use App\Notifications\ReferralCreatedNotification;
use App\Notifications\UrgentBloodPressureNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Single entry point for generating in-app notifications from real system
 * events. Every method is called only AFTER an actual record/state transition
 * has been persisted (never from a page render), so notifications are never
 * re-created just because a dashboard loaded.
 */
class SystemNotificationService
{
    /**
     * All active admin accounts. Admins receive clinic-wide awareness
     * notifications for clinical and referral activity they can view.
     *
     * @return Collection<int, User>
     */
    private function adminRecipients(): Collection
    {
        return User::where('role', 'admin')->get();
    }

    /**
     * Recipients for a patient-specific clinical event:
     * the patient's assigned staff member (when assigned) plus all admins.
     *
     * @return Collection<int, User>
     */
    private function clinicalRecipients(Patient $patient): Collection
    {
        $recipients = collect();

        if ($patient->assigned_staff_id) {
            $assigned = User::find($patient->assigned_staff_id);
            if ($assigned && $assigned->role === 'staff') {
                $recipients->push($assigned);
            }
        }

        foreach ($this->adminRecipients() as $admin) {
            $recipients->push($admin);
        }

        return $recipients->unique('id')->values();
    }

    /**
     * The patient's latest prenatal visit carried an urgent BP finding
     * (BP-URG) and the urgency transitioned into URGENT_CLINICAL_REVIEW.
     */
    public function notifyUrgentBloodPressure(PrenatalVisit $visit): void
    {
        $recipients = $this->clinicalRecipients($visit->patient);
        Notification::send(
            $recipients,
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
            $this->clinicalRecipients($visit->patient),
            new PendingRepeatBloodPressureNotification($visit)
        );
    }

    /**
     * A new pending referral was created for a patient.
     */
    public function notifyReferralCreated(Referral $referral): void
    {
        Notification::send(
            $this->adminRecipients(),
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
            $this->adminRecipients(),
            new ReferralClosedNotification($referral)
        );
    }
}