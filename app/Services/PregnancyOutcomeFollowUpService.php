<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\User;
use App\Support\PregnancyOutcomeVocabulary;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 17D — authoritative owner of the persisted follow-up observation
 * writes for Pregnancy Outcome Monitoring.
 *
 * Ownership boundary (SERVER-CONTROLLED, never client-supplied):
 *   - only follow_up_status, follow_up_recorded_at (server now) and
 *     follow_up_recorded_by (acting staff) are written.
 *   - Client input NEVER sets the observation type, the recorded-at timestamp
 *     or the recorded-by identity: the acting user and the server clock are
 *     always used regardless of what the controller receives.
 *
 * Sparse contract:
 *   - one PregnancyOutcome row per patient (patient_id UNIQUE). A blank
 *     placeholder row is created when none exists; otherwise the existing row
 *     is reused. follow_up_records REPLACE the previous observation because
 *     the schema stores only the latest follow-up facts.
 *   - patient.status must be exactly ONGOING and the outcome must not already
 *     be confirmed (a confirmed delivery is FINAL and forbids follow-up).
 *   - the EDD must have already passed (today > EDD) for the follow-up
 *     workflow to be open. NOT_YET_DUE pregnancies (null EDD, today, or
 *     future) must reject follow-up observations. Passing the EDD never
 *     implies DELIVERED and changes no clinical facts.
 *
 * The eligibility rule lives in PregnancyOutcomeMonitoringService
 * (isFollowUpEligible / isEddPassed); this service delegates to it so the
 * backend authority and the presentation can never disagree.
 *
 * Never touched here: patient.status, delivery_date, para, babies, referrals,
 * outcome_type / confirmation provenance, and notes. Follow-up transitions are
 * recorded in the AuditLog by the controller instead of abusing `notes`.
 */
class PregnancyOutcomeFollowUpService
{
    public function __construct(
        private PregnancyOutcomeMonitoringService $eligibility,
    ) {
    }

    public function recordStillPregnant(Patient $patient, User $actor): PregnancyOutcome
    {
        return $this->record(
            $patient,
            $actor,
            PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED
        );
    }

    public function recordUnableToContact(Patient $patient, User $actor): PregnancyOutcome
    {
        return $this->record(
            $patient,
            $actor,
            PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT
        );
    }

    private function record(Patient $patient, User $actor, string $status): PregnancyOutcome
    {
        return DB::transaction(function () use ($patient, $actor, $status) {
            // Re-read the patient under the transaction's lock, never trust the
            // controller-loaded (possibly stale) model. TOCTOU protection.
            $locked = Patient::query()
                ->whereKey($patient->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->trashed()) {
                throw new DomainException('This patient record is not found.');
            }

            $this->assertEligible($locked);

            $outcome = PregnancyOutcome::query()
                ->where('patient_id', $locked->id)
                ->lockForUpdate()
                ->first();

            if ($outcome && $outcome->hasConfirmedOutcome()) {
                throw new DomainException('This pregnancy already has a confirmed outcome and can no longer accept follow-up observations.');
            }

            // Authoritative boundary: follow-up is open only once the EDD has
            // passed. Not-yet-due / null / today EDD must reject; passing the
            // EDD never implies delivery. Delegates to monitoring so
            // presentation and backend authority share one rule.
            if (! $this->eligibility->isFollowUpEligible($locked, now())) {
                throw new DomainException(
                    'Follow-up observations are only accepted once the expected delivery date has passed.'
                );
            }

            $observation = [
                'follow_up_status' => $status,
                'follow_up_recorded_at' => now(),
                'follow_up_recorded_by' => $actor->id,
            ];

            if ($outcome) {
                $outcome->update($observation);
            } else {
                $outcome = PregnancyOutcome::create(
                    array_merge(['patient_id' => $locked->id], $observation)
                );
            }

            return $outcome->fresh();
        });
    }

    private function assertEligible(Patient $patient): void
    {
        if ($patient->status === 'DELIVERED') {
            throw new DomainException('A delivered pregnancy can no longer record follow-up observations.');
        }

        if ($patient->status === 'REFERRED') {
            throw new DomainException('Legacy referred patient records do not accept outcome follow-up observations.');
        }

        if ($patient->status !== 'ONGOING') {
            throw new DomainException('This patient is not eligible for outcome follow-up.');
        }
    }
}