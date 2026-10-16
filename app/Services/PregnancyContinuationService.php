<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * DEPLA Phase 3H.3 - transactional staff-managed pregnancy continuation.
 *
 * OWNER OF THE ENTIRE WRITE TRANSACTION. The controller validates the
 * request and preserves the existing UX (pre-checks, messages, redirect);
 * this service re-verifies every clinical and ownership rule UNDER THE ROW
 * LOCK and commits the whole continuation atomically:
 *
 *   lock old episode -> recheck DELIVERED -> recheck duplicate ONGOING
 *   -> verify account ownership / eligibility -> refuse foreign link
 *   evidence -> create new episode -> ensure verified links (old+new)
 *   -> clear old patients.user_id -> assign new patients.user_id
 *   -> structured audit -> commit.
 *
 * ARCHITECTURE CONTRACT (Option B)
 * --------------------------------
 * - patients.user_id remains the ONLY current-pregnancy pointer; the
 *   transfer below is the only place it moves between episodes.
 * - user_pregnancy_links records durable verified-ownership history; it
 *   never selects the current pregnancy and never grants portal access.
 * - The old pointer is cleared BEFORE the new pointer is set, inside the
 *   same transaction, so UNIQUE(patients.user_id) is never violated and
 *   no intermediate state is visible to concurrent readers (InnoDB
 *   REPEATABLE READ snapshots / SQLite single-writer).
 *
 * OWNERSHIP RULES
 * ---------------
 * - Only the login already linked through patients.user_id may continue.
 * - A linked account must exist, be active (not soft-deleted) and have
 *   role 'patient'; archived accounts are refused, never restored.
 * - Foreign verified-link evidence on the old episode is a conflict:
 *   the operation refuses instead of reassigning or deleting another
 *   user's link.
 * - An unlinked episode continues unlinked: no account is invented and
 *   no link is created from demographic similarity (names/birthdates
 *   remain a clinical duplicate safeguard ONLY).
 *
 * CONCURRENCY
 * -----------
 * - Two submissions against the SAME old episode serialize on
 *   lockForUpdate; the loser re-checks ONGOING inside its lock and is
 *   refused, so no duplicate episodes are created.
 * - Lock order is always: patient row -> link rows -> audit row.
 * - No automatic retries: a clinical transfer fails safely and staff
 *   resubmits after reloading.
 * - RESIDUAL LIMITATION (documented, unchanged from the inspection):
 *   two DIFFERENT unlinked old rows that share demographics are not
 *   serialized by this lock; that race predates this phase.
 *
 * No request input is trusted: lmp/edd/address/contact_number arrive
 * already validated by the controller's existing rules; every ownership
 * decision is derived from the locked database state.
 */
class PregnancyContinuationService
{
    /**
     * Continue a DELIVERED episode with a new ONGOING episode, preserving
     * the existing login when one is linked.
     *
     * @param array{lmp: string, edd: string, address: string, contact_number: string} $validated
     *
     * @throws DomainException when a clinical or ownership rule refuses
     */
    public function continueFromDelivered(Patient $oldPatient, User $actor, array $validated): Patient
    {
        return DB::transaction(function () use ($oldPatient, $actor, $validated) {
            // Re-fetch under the transaction's lock rather than trusting the
            // controller-loaded model (TOCTOU protection). The controller's
            // findOrFail excludes archived rows, so a soft-deleted episode
            // stays a 404 here as well.
            $locked = Patient::whereKey($oldPatient->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new DomainException('This patient record no longer exists.');
            }

            if ($locked->status !== 'DELIVERED') {
                throw new DomainException('New pregnancy can only be started from a delivered patient record.');
            }

            // Duplicate-ONGOING recheck under the lock. Demographics are a
            // CLINICAL safeguard only - they never grant or infer portal
            // ownership anywhere in this service.
            $hasActivePregnancy = Patient::where('first_name', $locked->first_name)
                ->where('last_name', $locked->last_name)
                ->where('birthdate', $locked->birthdate)
                ->where('status', 'ONGOING')
                ->exists();

            if ($hasActivePregnancy) {
                throw new DomainException('This patient already has an active ongoing pregnancy record.');
            }

            // Ownership evidence consistency. For a linked episode only the
            // pointer's own account may hold link rows; for an unlinked
            // episode ANY link row is foreign evidence. Either way the
            // operation refuses instead of reassigning another user's link.
            $foreignLink = UserPregnancyLink::where('patient_id', $locked->id)
                ->when(
                    $locked->user_id !== null,
                    fn ($query) => $query->where('user_id', '!=', $locked->user_id)
                )
                ->exists();

            if ($foreignLink) {
                throw new DomainException(
                    'This pregnancy record has conflicting account-link history and cannot be continued automatically. Please contact the administrator.'
                );
            }

            $userId = $locked->user_id;

            if ($userId !== null) {
                // The pointer's account must be a live patient login. An
                // archived (soft-deleted) or non-patient account is refused -
                // never restored, never partially linked.
                $account = User::withTrashed()->find($userId);

                if ($account === null || $account->trashed()) {
                    throw new DomainException(
                        'The linked patient login account is archived and cannot be continued. Please restore the account before starting a new pregnancy.'
                    );
                }

                if ($account->role !== 'patient') {
                    throw new DomainException(
                        'The linked login account is not a patient account and cannot be continued. Please contact the administrator.'
                    );
                }
            }

            $new = $this->createEpisode($locked, $validated);

            if ($userId !== null) {
                // Durable verified-ownership history for BOTH episodes
                // (idempotent per pair). Written while holding the old-row
                // lock, so every conforming writer serializes here.
                $this->ensureVerifiedLink($locked->id, $userId, $actor);
                $this->ensureVerifiedLink($new->id, $userId, $actor);

                // Pointer transfer: clear the old episode first so
                // UNIQUE(patients.user_id) is never violated mid-transaction,
                // then assign the same account to the new episode.
                $locked->user_id = null;
                $locked->save();

                $new->user_id = $userId;
                $new->save();
            }

            // Structured audit INSIDE the transaction: either the whole
            // continuation and its audit entry both persist, or neither
            // does. ID-based description - no patient names, no password or
            // credential material, never auth()->id() ?? 1.
            AuditLog::create([
                'user_id' => $actor->id,
                'patient_id' => $new->id,
                'action' => 'CREATE',
                'module' => 'PATIENT',
                'description' => $userId !== null
                    ? sprintf(
                        'Started new pregnancy record (patient ID %d) continuing from delivered pregnancy (patient ID %d). Login account (user ID %d) transferred to the new episode; verified history links recorded for both episodes.',
                        $new->id,
                        $locked->id,
                        $userId
                    )
                    : sprintf(
                        'Started new pregnancy record (patient ID %d) from delivered pregnancy (patient ID %d). No login account was linked; no account relationship was created.',
                        $new->id,
                        $locked->id
                    ),
            ]);

            return $new;
        });
    }

    /**
     * Create the new ONGOING episode with the EXISTING clinical
     * field-copying behavior (identity, obstetric history and static
     * profile copied; address/contact/lmp/edd taken from the validated
     * request; age recomputed from the birthdate; delivery state reset).
     * user_id is deliberately absent here - it is assigned only by the
     * explicit pointer transfer above, never through mass assignment.
     */
    private function createEpisode(Patient $old, array $validated): Patient
    {
        return Patient::create([
            'first_name' => $old->first_name,
            'middle_name' => $old->middle_name,
            'last_name' => $old->last_name,
            'birthdate' => $old->birthdate,
            'age' => Carbon::parse($old->birthdate)->age,

            'address' => $validated['address'],
            'contact_number' => $validated['contact_number'],

            'email' => $old->email,
            'civil_status' => $old->civil_status,
            'philhealth_member' => $old->philhealth_member,
            'philhealth_number' => $old->philhealth_number,

            'gravida' => $old->gravida + 1,
            'para' => $old->para,
            'previous_cs' => $old->previous_cs,
            'miscarriage' => $old->miscarriage,

            'lmp' => $validated['lmp'],
            'edd' => $validated['edd'],
            'status' => 'ONGOING',
            'delivery_date' => null,
        ]);
    }

    /**
     * Ensure a verified user-to-pregnancy link row exists for the EXPLICIT
     * authorized pairing. Idempotent: an existing pair is left untouched.
     * verified_by records the acting staff member; verified_at is the
     * pairing timestamp. Never called with demographic evidence.
     */
    private function ensureVerifiedLink(int $patientId, int $userId, User $actor): void
    {
        $exists = UserPregnancyLink::where('user_id', $userId)
            ->where('patient_id', $patientId)
            ->exists();

        if ($exists) {
            return;
        }

        $link = new UserPregnancyLink();
        $link->user_id = $userId;
        $link->patient_id = $patientId;
        $link->verified_by = $actor->id;
        $link->verified_at = now();
        $link->save();
    }
}
