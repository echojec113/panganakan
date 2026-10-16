<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\User;
use App\Services\PregnancyHistoryAssociationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * DEPLA Task B - idempotent reconciliation of EXISTING Patient Portal
 * accounts with legacy DELIVERED pregnancy episodes that were never
 * linked (accounts provisioned before automatic association existed).
 *
 * Reuses the exact PregnancyHistoryAssociationService logic that account
 * creation uses: the approved four-field identity match (first + last
 * exact, middle name + birthdate NULL-safe), live DELIVERED candidates
 * only, and the conflict doctrine that refuses - never reassigns - any
 * cluster whose candidate carries foreign ownership.
 *
 * SAFEGUARDS
 * ----------
 * - DRY RUN by default: no database write of any kind happens unless the
 *   operator explicitly passes --apply.
 * - Output is AGGREGATE COUNTS ONLY - no patient names, emails, episode
 *   IDs or account identifiers are ever printed.
 * - Only accounts that hold a live current pointer (patients.user_id)
 *   are considered; archived episodes are excluded by the shared scope;
 *   anchors whose account is archived are skipped, never restored.
 * - Conflicting clusters are refused as a whole and only counted; other
 *   clean clusters are still processed.
 * - Newly created links carry verified_by = NULL (no staff verifier
 *   identity is reliably available from a console command) and an
 *   ID-only audit row attributed to the owning account.
 * - Repeated execution never creates duplicate links: already-linked
 *   pairs are counted as preserved and skipped (Phase 3H.2 pair-unique
 *   index is the race backstop).
 * - Clinical records, pregnancy statuses and current-pregnancy pointers
 *   are never modified.
 *
 * This command must only be run against a reviewed, backed-up database
 * and never automatically; it is an explicit operational tool.
 */
class ReconcilePregnancyHistoryLinks extends Command
{
    protected $signature = 'depla:reconcile-pregnancy-history
        {--apply : Write the missing verified ownership links (default is a read-only dry run)}';

    protected $description = 'Reconcile existing Patient Portal accounts with legacy DELIVERED pregnancy episodes using the approved four-field identity match. Dry-run by default; aggregate counts only.';

    public function handle(PregnancyHistoryAssociationService $associations): int
    {
        $apply = (bool) $this->option('apply');

        // Live accounts with a current pointer: each pointer episode is an
        // explicit account-to-person anchor. SoftDeletes scope hides
        // archived episodes automatically.
        $anchors = Patient::query()
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'first_name', 'middle_name', 'last_name', 'birthdate']);

        $scanned = 0;
        $missingTotal = 0;
        $preservedTotal = 0;
        $refusedClusters = 0;
        $skippedArchivedAccounts = 0;
        $createdTotal = 0;

        foreach ($anchors as $anchor) {
            $scanned++;

            $owner = User::withTrashed()->find($anchor->user_id);

            // Defensive: a dangling pointer (hard-deleted account) is
            // skipped, never repaired here. An archived account is counted
            // and skipped - never restored, never linked posthumously.
            if ($owner === null || $owner->trashed()) {
                $skippedArchivedAccounts++;
                continue;
            }

            $plan = $associations->planFor($anchor, (int) $anchor->user_id);

            if ($plan['conflict']) {
                $refusedClusters++;
                continue;
            }

            $missingTotal += count($plan['missing']);
            $preservedTotal += count($plan['preserved']);

            if ($apply && $plan['missing'] !== []) {
                $createdTotal += DB::transaction(
                    fn (): int => count($associations->applyPlan(
                        $plan,
                        $anchor,
                        $owner,
                        $owner,
                        null
                    ))
                );
            }
        }

        $this->info('Mode: ' . ($apply ? 'APPLY' : 'DRY RUN (no changes written)'));
        $this->info('Account anchor episodes scanned: ' . $scanned);
        $this->info('Missing verified ownership links: ' . $missingTotal);
        $this->info('Matching episodes already linked (preserved): ' . $preservedTotal);
        $this->info('Conflicting clusters refused: ' . $refusedClusters);
        $this->info('Skipped (archived or missing account): ' . $skippedArchivedAccounts);
        $this->info(
            $apply
                ? 'Links created: ' . $createdTotal
                : 'Links created: 0 (dry run - re-run with --apply to write)'
        );

        return self::SUCCESS;
    }
}
