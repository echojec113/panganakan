<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use Illuminate\Database\Eloquent\Collection;

/**
 * DEPLA Task B — approved four-field identity association of legacy
 * DELIVERED pregnancy episodes with an existing Patient Portal login.
 *
 * SHARED ownership logic for BOTH writers:
 *   - PatientAccountCreationService (inside the provisioning transaction)
 *   - depla:reconcile-pregnancy-history (existing-account reconciliation)
 *
 * IDENTITY RULE (approved; reuses the exact PatientViewController::
 * pregnanciesFor semantics): first_name + last_name exact, middle_name
 * NULL-safe, birthdate NULL-safe. No fuzzy matching, no LOWER/TRIM
 * normalization, and email / phone / address are NEVER identity keys.
 * Demographics alone never discover an ACCOUNT: association only ever
 * extends an explicitly provisioned account-to-episode anchor (the
 * staff action on one specific Patient Profile) - never a
 * demographic-only pairing.
 *
 * SCOPE: live (non-archived) episodes with status exactly DELIVERED,
 * excluding the anchor episode itself. ONGOING, REFERRED and archived
 * siblings are never candidates and are never linked. patients.user_id
 * stays the only portal pointer; a link row alone still never grants
 * Patient Portal route access.
 *
 * CONFLICT DOCTRINE: a candidate carrying ANY foreign ownership (a
 * patients.user_id pointer or a user_pregnancy_links row belonging to
 * another account) refuses the WHOLE cluster - ownership is never
 * reassigned, never silently skipped per-row and never repaired here.
 * A candidate already linked to the SAME account is preserved untouched
 * (idempotency). The birthdate is compared by passing the cast Carbon
 * value straight into where(), exactly like PatientViewController::
 * pregnanciesFor: the query grammar serializes it with the CONNECTION's
 * date format, so the comparison matches the stored value identically on
 * SQLite ('Y-m-d H:i:s') and MySQL ('Y-m-d').
 */
class PregnancyHistoryAssociationService
{
    /**
     * Live DELIVERED episodes sharing the anchor's exact four-field
     * identity, excluding the anchor episode. The SoftDeletes scope hides
     * archived rows automatically.
     *
     * @return Collection<int, Patient>
     */
    public function sameIdentityDeliveredEpisodes(Patient $anchor): Collection
    {
        return Patient::query()
            ->whereKeyNot($anchor->getKey())
            ->where('status', 'DELIVERED')
            ->where('first_name', $anchor->first_name)
            ->where('last_name', $anchor->last_name)
            ->when(
                $anchor->middle_name !== null,
                fn ($query) => $query->where('middle_name', $anchor->middle_name),
                fn ($query) => $query->whereNull('middle_name')
            )
            ->when(
                $anchor->birthdate !== null,
                fn ($query) => $query->where('birthdate', $anchor->birthdate),
                fn ($query) => $query->whereNull('birthdate')
            )
            ->get();
    }

    /**
     * Assess the anchor's DELIVERED cluster for the given account WITHOUT
     * writing anything. The caller decides whether a conflict aborts
     * account creation or refuses the cluster during reconciliation.
     *
     * @return array{
     *     conflict: bool,
     *     conflict_patient_id: int|null,
     *     missing: list<int>,
     *     preserved: list<int>
     * }
     */
    public function planFor(Patient $anchor, int $userId): array
    {
        $missing = [];
        $preserved = [];

        foreach ($this->sameIdentityDeliveredEpisodes($anchor) as $candidate) {
            // Any pointer on a candidate episode belongs to another account
            // (the anchor holds this account's only pointer; patients.user_id
            // is UNIQUE) - a data anomaly that refuses the whole cluster.
            if ($candidate->user_id !== null) {
                return $this->conflictPlan($candidate->getKey());
            }

            // Ownership-link evidence: any row for a DIFFERENT account is a
            // foreign conflict; rows for THIS account are preserved as-is.
            $linkedUserIds = UserPregnancyLink::where('patient_id', $candidate->getKey())
                ->pluck('user_id');

            if ($linkedUserIds->contains(fn ($linkedUserId): bool => (int) $linkedUserId !== $userId)) {
                return $this->conflictPlan($candidate->getKey());
            }

            if ($linkedUserIds->contains($userId)) {
                $preserved[] = $candidate->getKey();
                continue;
            }

            $missing[] = $candidate->getKey();
        }

        return [
            'conflict' => false,
            'conflict_patient_id' => null,
            'missing' => $missing,
            'preserved' => $preserved,
        ];
    }

    /**
     * Insert the missing verified ownership links + one audit row per new
     * link. Callers must have verified the plan carries no conflict and
     * must already hold the enclosing transaction when called from account
     * creation. Idempotent by construction: only the plan's `missing`
     * episode IDs are written; `preserved` pairs are never re-inserted
     * (the Phase 3H.2 pair-unique index is the race backstop).
     *
     * Audit rows use record IDs only - never patient names or any other
     * identifying detail. `verified_by` records the acting staff member
     * when one exists (account creation); console reconciliation passes
     * null because no staff verifier identity is reliably available.
     *
     * @return list<int> the episode IDs newly linked
     */
    public function applyPlan(
        array $plan,
        Patient $anchor,
        User $ownerAccount,
        User $auditActor,
        ?int $verifiedBy = null
    ): array {
        $created = [];

        foreach ($plan['missing'] as $episodeId) {
            $link = new UserPregnancyLink();
            $link->user_id = $ownerAccount->getKey();
            $link->patient_id = $episodeId;
            $link->verified_by = $verifiedBy;
            $link->verified_at = now();
            $link->save();

            AuditLog::create([
                'user_id' => $auditActor->getKey(),
                'patient_id' => $episodeId,
                'action' => 'PREGNANCY_HISTORY_LINKED',
                'module' => 'PATIENT',
                'description' => $verifiedBy === null
                    ? sprintf(
                        'Verified pregnancy history link auto-associated by console reconciliation: login user ID %d <- pregnancy episode ID %d (same four-field identity as patient ID %d).',
                        $ownerAccount->getKey(),
                        $episodeId,
                        $anchor->getKey()
                    )
                    : sprintf(
                        'Verified pregnancy history link auto-associated during account creation: login user ID %d <- pregnancy episode ID %d (same four-field identity as patient ID %d).',
                        $ownerAccount->getKey(),
                        $episodeId,
                        $anchor->getKey()
                    ),
            ]);

            $created[] = $episodeId;
        }

        return $created;
    }

    /**
     * @return array{conflict: bool, conflict_patient_id: int, missing: list<int>, preserved: list<int>}
     */
    private function conflictPlan(int $patientId): array
    {
        return [
            'conflict' => true,
            'conflict_patient_id' => $patientId,
            'missing' => [],
            'preserved' => [],
        ];
    }
}
