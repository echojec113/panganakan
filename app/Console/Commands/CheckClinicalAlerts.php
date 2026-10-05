<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Time-based clinical alert sweep. Scheduled from routes/console.php so the
 * alerts do NOT depend on a user opening the dashboard.
 *
 * Scope (read-only + notifications):
 *   - EDD Approaching: ongoing pregnancy inside the EDD - 7 days .. EDD
 *     window.
 *   - Past EDD / Needs Review: ongoing pregnancy past its EDD with no
 *     recorded completion outcome. This is a REVIEW alert only - it never
 *     changes patient status, never records an outcome and never creates a
 *     newborn or modifies LMP/EDD.
 *
 * Repeat BP Overdue is deliberately NOT implemented here: the system has no
 * authoritative overdue interval for a pending repeat measurement.
 *
 * Idempotency: every alert carries a stable event_key (one per pregnancy)
 * that is checked against the notifications ledger before sending, so the
 * command can be run any number of times per day without creating
 * duplicates.
 */
class CheckClinicalAlerts extends Command
{
    /** Days before the EDD at which the approaching alert first fires. */
    public const EDD_APPROACHING_DAYS = 7;

    protected $signature = 'notifications:check-clinical-alerts';

    protected $description = 'Send idempotent time-based clinical notifications (EDD approaching, past EDD)';

    public function handle(
        SystemNotificationService $notifications,
        PregnancyOutcomeMonitoringService $monitoring
    ): int {
        $today = Carbon::today();

        $this->info('Processing clinical alerts for: ' . $today->toDateString());

        $approachingSent = $this->checkEddApproaching($today, $notifications, $monitoring);
        $pastEddSent = $this->checkPastEdd($today, $notifications, $monitoring);

        $this->info('EDD approaching notifications sent: ' . $approachingSent);
        $this->info('Past EDD notifications sent: ' . $pastEddSent);
        $this->info('Clinical alerts processed successfully.');

        return Command::SUCCESS;
    }

    /**
     * ONGOING pregnancy whose EDD falls inside [today, today + 7 days].
     *
     * @return int Notifications actually sent (already-sent pregnancies are
     *              skipped by the service's event_key guard).
     */
    private function checkEddApproaching(
        Carbon $today,
        SystemNotificationService $notifications,
        PregnancyOutcomeMonitoringService $monitoring
    ): int {
        $patients = Patient::query()
            ->where('status', 'ONGOING')
            ->whereNotNull('edd')
            ->where('edd', '>=', $today->toDateString())
            ->where('edd', '<=', $today->copy()->addDays(self::EDD_APPROACHING_DAYS)->toDateString())
            ->get();

        $sent = 0;

        foreach ($patients as $patient) {
            // Authoritative EDD arithmetic (same helper the profile uses).
            $days = $monitoring->daysUntilOrPastEdd($patient, $today);

            if ($days === null || $days < 0 || $days > self::EDD_APPROACHING_DAYS) {
                continue;
            }

            // A recorded completion outcome means the pregnancy is no longer
            // "approaching" anything, regardless of the stored status row.
            if ($patient->pregnancyOutcome?->hasConfirmedOutcome()) {
                continue;
            }

            if ($notifications->notifyEddApproaching($patient)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * ONGOING pregnancy past its EDD with no confirmed outcome. The
     * authoritative follow-up gate (isFollowUpEligible) requires
     * status = ONGOING, no confirmed outcome and today > EDD.
     *
     * @return int Notifications actually sent.
     */
    private function checkPastEdd(
        Carbon $today,
        SystemNotificationService $notifications,
        PregnancyOutcomeMonitoringService $monitoring
    ): int {
        $patients = Patient::query()
            ->where('status', 'ONGOING')
            ->whereNotNull('edd')
            ->where('edd', '<', $today->toDateString())
            ->get();

        $sent = 0;

        foreach ($patients as $patient) {
            if (! $monitoring->isFollowUpEligible($patient, $today)) {
                continue;
            }

            if ($notifications->notifyPastEddNeedsReview($patient)) {
                $sent++;
            }
        }

        return $sent;
    }
}
