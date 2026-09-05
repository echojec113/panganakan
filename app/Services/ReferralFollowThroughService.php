<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Owns the referral follow-through workflow state transitions.
 *
 * Status model (Phase 16D): `Referral.status` is independent from the
 * pregnancy lifecycle (`patient.status`). Creating a referral never changes
 * the patient status; referred pregnancies remain ONGOING.
 *
 * Allowed transitions (new referrals start as "Pending"):
 *   - Pending    -> Completed  (clinic-recorded follow-through)
 *   - Pending    -> Refused    (patient refused; waiver + notes recorded)
 *   - Pending    -> Cancelled  (referral the clinic chose not to pursue)
 *
 * Closed statuses (Completed/Refused/Cancelled) are terminal. They can never
 * transition again nor reopen to Pending; a follow-up is represented by a new
 * referral row so history is preserved.
 *
 * Concurrency: every transition reloads the row inside a transaction with a
 * pessimistic lock so two parallel requests cannot double-complete or
 * complete a referral that was just refused.
 */
class ReferralFollowThroughService
{
    /**
     * Record that clinic staff closed the referral as completed/fulfilled
     * based on the information available to the clinic.
     */
    public function complete(Referral $referral, User $actor): Referral
    {
        $this->transition($referral, 'Completed', function (Referral $row) {
            $row->completed_at = now();
            $row->refusal_notes = null;
            $row->refusal_recorded_at = null;
            $row->refusal_recorded_by = null;
            $row->waiver_signed = false;
        });

        return $referral->refresh();
    }

    /**
     * Record that the patient refused the referral. Requires the staff
     * narrative and a boolean "physical waiver signed/recorded" flag. The
     * refusal is server-stamped; the browser can never provide timestamps or
     * actor ids. `completed_at` stays null because the referral never closed.
     */
    public function refuse(
        Referral $referral,
        User $actor,
        string $notes,
        bool $waiverSigned
    ): Referral {
        if (trim($notes) === '') {
            throw new DomainException('A refusal note is required to record a refusal.');
        }

        $this->transition($referral, 'Refused', function (Referral $row) use ($actor, $notes, $waiverSigned) {
            $row->completed_at = null;
            $row->refusal_notes = $notes;
            $row->refusal_recorded_at = now();
            $row->refusal_recorded_by = $actor->id;
            $row->waiver_signed = (bool) $waiverSigned;
        });

        return $referral->refresh();
    }

    /**
     * Smallest admin transition: cancel a pending referral. Keeps the
     * referral history visible and distinct from a patient refusal.
     *
     * Original `referral.notes` captured at creation are NEVER overwritten:
     * there is no dedicated cancellation column and no migration is allowed,
     * so cancellation stores nothing extra (the audit entry records the
     * transition).
     */
    public function cancel(Referral $referral, User $actor): Referral
    {
        $this->transition($referral, 'Cancelled', function (Referral $row) {
            $row->completed_at = null;
            $row->refusal_notes = null;
            $row->refusal_recorded_at = null;
            $row->refusal_recorded_by = null;
            $row->waiver_signed = false;
        });

        return $referral->refresh();
    }

    /**
     * Assert the transition is allowed, apply $mutator to a locked row inside
     * a transaction, and persist. Throws DomainException when the referral is
     * not currently Pending (closed statuses are terminal).
     */
    private function transition(Referral $referral, string $to, callable $mutated): void
    {
        DB::transaction(function () use ($referral, $to, $mutated) {
            $row = Referral::whereKey($referral->id)->lockForUpdate()->first();

            if (! $row) {
                throw new DomainException('Referral no longer exists.');
            }

            if ($row->status !== 'Pending') {
                throw new DomainException(
                    'Referral is already ' . $row->status . ' and cannot transition to ' . $to . '.'
                );
            }

            $row->status = $to;
            $mutated($row);
            $row->save();
        });
    }
}