<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Support\PregnancyOutcomeVocabulary;
use Carbon\CarbonInterface;

/**
 * Sprint 17D — owns the READ/DERIVATION logic for pregnancy outcome
 * monitoring. This service NEVER writes clinical or lifecycle state.
 *
 * EDD PASSED != DELIVERED. A pregnancy becomes DELIVERED only through the
 * explicit Sprint 17C confirmed-delivery workflow (RecordingService), never
 * through inference from a passed EDD. Passing the EDD merely opens the
 * outcome-confirmation / follow-up window handled here.
 *
 * Derived states (never persisted — the persisted facts stay in
 * patients.status and pregnancy_outcomes):
 *   - RESOLVED:               confirmed delivery outcome recorded.
 *   - STILL_PREGNANT_CONFIRMED: ONGOING pregnancy whose latest recorded
 *                               follow-up observation confirms she is still
 *                               pregnant (within the recent follow-up window).
 *   - UNABLE_TO_CONTACT:      ONGOING pregnancy whose latest recorded
 *                             follow-up observation failed to reach her
 *                             (within the recent follow-up window).
 *   - CONFIRMATION_REQUIRED:  EDD already passed, no confirmed outcome, and
 *                             no recent follow-up observation — outcome
 *                             confirmation must be requested.
 *   - NOT_YET_DUE:            EDD not yet reached (or missing) — monitoring
 *                             not yet due.
 *   - LEGACY_DELIVERED:       DELIVERED row that predates Sprint 17C and has
 *                             no confirmed outcome record. Kept as a valid
 *                             historical record, never auto-confirmed.
 *   - LEGACY_REFERRED:        Legacy REFERRED lifecycle status (Phase 16D).
 *                             Referred pregnancies that are still ONGOING
 *                             today are tracked under their real status.
 *   - INVARIANT_VIOLATION:    Diagnostic derived state for an ONGOING patient
 *                             that already has a confirmed outcome record —
 *                             the lifecycle and the outcome are mutually
 *                             inconsistent. The service NEVER rewrites
 *                             patient.status to hide this; it surfaces the
 *                             inconsistency for manual review.
 *
 * Follow-up recency window: an observation is "recent" when it was recorded
 * within FOLLOW_UP_VALID_DAYS days of the asOf date (inclusive). Older
 * observations expire back to CONFIRMATION_REQUIRED so a stale "still
 * pregnant" claim never suppresses outcome confirmation forever.
 */
class PregnancyOutcomeMonitoringService
{
    public const FOLLOW_UP_VALID_DAYS = 7;

    public const STATE_RESOLVED = 'RESOLVED';
    public const STATE_STILL_PREGNANT_CONFIRMED = 'STILL_PREGNANT_CONFIRMED';
    public const STATE_UNABLE_TO_CONTACT = 'UNABLE_TO_CONTACT';
    public const STATE_CONFIRMATION_REQUIRED = 'CONFIRMATION_REQUIRED';
    public const STATE_NOT_YET_DUE = 'NOT_YET_DUE';
    public const STATE_LEGACY_DELIVERED = 'LEGACY_DELIVERED';
    public const STATE_LEGACY_REFERRED = 'LEGACY_REFERRED';
    public const STATE_INVARIANT_VIOLATION = 'INVARIANT_VIOLATION';

    /**
     * @var array<string, string> Friendly, full-sentence labels. Single
     *                             source of truth — Blade templates must never
     *                             str_replace() raw enum strings.
     */
    public const STATE_LABELS = [
        self::STATE_CONFIRMATION_REQUIRED => 'Outcome Confirmation Required',
        self::STATE_STILL_PREGNANT_CONFIRMED => 'Still Pregnant — Confirmed',
        self::STATE_UNABLE_TO_CONTACT => 'Unable to Contact',
        self::STATE_RESOLVED => 'Confirmed Delivery',
        self::STATE_NOT_YET_DUE => 'Monitoring Not Yet Due',
        self::STATE_LEGACY_DELIVERED => 'Historical Delivered Record',
        self::STATE_LEGACY_REFERRED => 'Legacy Referred Record',
        self::STATE_INVARIANT_VIOLATION => 'Outcome Data Invariant Violation — Requires Review',
    ];

    public static function stateLabel(string $state): string
    {
        return self::STATE_LABELS[$state] ?? $state;
    }

    /** Slug => derived state, used for the page filter so raw enums never
     *  leak into URLs or select option values. */
    public const STATE_FILTERS = [
        'confirmation-required' => self::STATE_CONFIRMATION_REQUIRED,
        'still-pregnant' => self::STATE_STILL_PREGNANT_CONFIRMED,
        'unable-to-contact' => self::STATE_UNABLE_TO_CONTACT,
        'resolved' => self::STATE_RESOLVED,
    ];

    /**
     * Derive the current monitoring state for a single pregnancy episode.
     *
     * @param  CarbonInterface|null  $asOf  Deterministic "current date/time".
     *                                      Defaults to now() when omitted.
     */
    public function deriveState(Patient $patient, ?CarbonInterface $asOf = null): string
    {
        $asOf ??= now();

        if ($patient->status === 'DELIVERED') {
            $outcome = $patient->pregnancyOutcome;

            return $outcome && $outcome->hasConfirmedOutcome()
                ? self::STATE_RESOLVED
                : self::STATE_LEGACY_DELIVERED;
        }

        if ($patient->status === 'REFERRED') {
            return self::STATE_LEGACY_REFERRED;
        }

        if ($patient->status !== 'ONGOING') {
            return self::STATE_INVARIANT_VIOLATION;
        }

        // An ONGOING patient must not already carry a confirmed outcome.
        // Surface the inconsistency for review; never hide or rewrite it.
        $outcome = $patient->pregnancyOutcome;
        if ($outcome && $outcome->hasConfirmedOutcome()) {
            return self::STATE_INVARIANT_VIOLATION;
        }

        if ($patient->edd === null) {
            return self::STATE_NOT_YET_DUE;
        }

        $today = $asOf->copy()->startOfDay();

        if ($today->lte($patient->edd)) {
            return self::STATE_NOT_YET_DUE;
        }

        // EDD has passed and there is no confirmed outcome.
        if ($outcome === null || $outcome->follow_up_status === null || $outcome->follow_up_recorded_at === null) {
            return self::STATE_CONFIRMATION_REQUIRED;
        }

        if (! $this->isWithinFollowUpWindow($outcome->follow_up_recorded_at, $asOf)) {
            return self::STATE_CONFIRMATION_REQUIRED;
        }

        if ($outcome->follow_up_status === PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED) {
            return self::STATE_STILL_PREGNANT_CONFIRMED;
        }

        if ($outcome->follow_up_status === PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT) {
            return self::STATE_UNABLE_TO_CONTACT;
        }

        return self::STATE_CONFIRMATION_REQUIRED;
    }

    /**
     * True when a follow-up observation is still "recent" as of $asOf.
     *
     * Recent = recorded on a date within the last FOLLOW_UP_VALID_DAYS days
     * (inclusive) of the asOf date, so an observation made exactly 7 days ago
     * is still within the window and a stale cell never suppresses
     * confirmation forever.
     */
    public function isWithinFollowUpWindow(CarbonInterface $recordedAt, CarbonInterface $asOf): bool
    {
        $windowStart = $asOf->copy()->startOfDay()->subDays(self::FOLLOW_UP_VALID_DAYS);
        $windowEnd = $asOf->copy()->startOfDay();

        return $recordedAt->copy()->startOfDay()->between($windowStart, $windowEnd);
    }

    /**
     * @return array<string, int> State counts for a set of pregnancy episodes.
     */
    public function countByState(iterable $patients): array
    {
        $states = [];

        foreach ($patients as $patient) {
            $state = $patient instanceof Patient ? $this->deriveState($patient) : 'UNKNOWN';
            $states[$state] = ($states[$state] ?? 0) + 1;
        }

        return $states;
    }

    /**
     * Days remaining until EDD for an ONGOING pregnancy (positive), or days
     * past EDD (negative) once passed. null when EDD is unknown.
     */
    public function daysUntilOrPastEdd(Patient $patient, ?CarbonInterface $asOf = null): ?int
    {
        if ($patient->edd === null) {
            return null;
        }

        $asOf = $asOf ?? now();

        return (int) $asOf->copy()->startOfDay()->diffInDays($patient->edd->copy()->startOfDay(), false);
    }

    /**
     * True when the EDD has already passed relative to the server date.
     * "Passed" is a strict comparison against the calendar day (today > EDD),
     * so an EDD that falls on today remains NOT_YET_DUE and must not open the
     * follow-up workflow. A null EDD is never "passed". This single rule is
     * the authoritative gate shared by follow-up writes and presentation.
     *
     * @param  CarbonInterface|null  $asOf  Server date/time; defaults to now().
     */
    public function isEddPassed(Patient $patient, ?CarbonInterface $asOf = null): bool
    {
        if ($patient->edd === null) {
            return false;
        }

        $today = ($asOf ?? now())->copy()->startOfDay();

        return $today->gt($patient->edd->copy()->startOfDay());
    }

    /**
     * True when the patient may accept outcome follow-up observations.
     *
     * Requires ALL of:
     *   - patient.status === 'ONGOING'
     *   - no confirmed pregnancy outcome exists
     *   - patient.edd is not null
     *   - the EDD has already passed (today > EDD — opening the
     *     outcome-confirmation / follow-up window; NOT_YET_DUE EDD today or in
     *     the future, and null EDD, never accept follow-up).
     *
     * Nothing here implies delivery. A passed EDD is never auto-confirmed; it
     * merely makes the lifecycle eligible for the persisted follow-up
     * observations (STILL_PREGNANT_CONFIRMED / UNABLE_TO_CONTACT).
     */
    public function isFollowUpEligible(Patient $patient, ?CarbonInterface $asOf = null): bool
    {
        if ($patient->status !== 'ONGOING') {
            return false;
        }

        $outcome = $patient->pregnancyOutcome;
        if ($outcome && $outcome->hasConfirmedOutcome()) {
            return false;
        }

        return $this->isEddPassed($patient, $asOf);
    }
}