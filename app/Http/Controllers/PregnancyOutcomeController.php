<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\PregnancyOutcomeFollowUpService;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Support\PregnancyOutcomeVocabulary;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Sprint 17D — Pregnancy Outcome Monitoring.
 *
 * This controller only reads and derives; all writes go through the
 * dedicated services. Derivation happens in the monitoring service, not here.
 */
class PregnancyOutcomeController extends Controller
{
    private const PER_PAGE = 15;

    /** @var array<string, int> Deterministic sort priority for the queue. */
    private const STATE_ORDER = [
        PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED => 0,
        PregnancyOutcomeMonitoringService::STATE_STILL_PREGNANT_CONFIRMED => 1,
        PregnancyOutcomeMonitoringService::STATE_UNABLE_TO_CONTACT => 2,
        PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE => 3,
        PregnancyOutcomeMonitoringService::STATE_RESOLVED => 4,
        PregnancyOutcomeMonitoringService::STATE_LEGACY_DELIVERED => 5,
        PregnancyOutcomeMonitoringService::STATE_LEGACY_REFERRED => 6,
        PregnancyOutcomeMonitoringService::STATE_INVARIANT_VIOLATION => 7,
    ];

    public function __construct(
        private PregnancyOutcomeMonitoringService $monitoring,
        private PregnancyOutcomeFollowUpService $followUp,
    ) {
    }

    /**
     * Derived monitoring dataset: every non-trashed pregnancy episode is
     * classified into a monitoring state and the queue is ordered with the
     * most urgent action (confirmation required) first.
     *
     * Collection-level derivation is a deliberate, documented tradeoff: the
     * monitored population is bounded by lifecycle status (ONGOING / DELIVERED
     * / REFERRED), and the derived state cannot be expressed in SQL because it
     * combines EDD, confirmed-outcome presence and follow-up recency. At the
     * current capstone scale correctness is valued over SQL pre-filters; the
     * population is loaded once with the relations it needs.
     */
    public function index(Request $request)
    {
        $population = Patient::query()
            ->with([
                'pregnancyOutcome.followUpRecordedBy',
                'pregnancyOutcome.confirmedBy',
                'babies',
                'referrals',
            ])
            ->whereIn('status', ['ONGOING', 'DELIVERED', 'REFERRED'])
            ->orderByDesc('created_at')
            ->get();

        $stats = $this->monitoring->countByState($population);

        $rows = $population->map(fn (Patient $patient) => $this->rowFor($patient))->values();

        $search = trim((string) $request->query('search'));
        if ($search !== '') {
            $searchLower = strtolower($search);
            $rows = $rows->filter(function (array $row) use ($searchLower) {
                $patient = $row['patient'];

                return str_contains(strtolower((string) $patient->first_name), $searchLower)
                    || str_contains(strtolower((string) $patient->last_name), $searchLower)
                    || str_contains(strtolower((string) ($patient->middle_name ?? '')), $searchLower);
            })->values();
        }

        $stateFilterSlug = (string) $request->query('state');
        $stateFilter = PregnancyOutcomeMonitoringService::STATE_FILTERS[$stateFilterSlug] ?? '';
        if ($stateFilter !== '') {
            $rows = $rows->filter(fn (array $row) => $row['state'] === $stateFilter)->values();
        }

        $rows = $rows->sortBy(fn (array $row) => [
            self::STATE_ORDER[$row['state']] ?? 9,
            $row['days_until_edd'] ?? 0,
        ], SORT_REGULAR)->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('pregnancy-outcomes.index', compact('paginator', 'stats', 'search', 'stateFilter'));
    }

    /**
     * Record a "still pregnant" follow-up observation. Staff-only route.
     */
    public function recordStillPregnant(Request $request, $id)
    {
        return $this->recordFollowUp($request, $id, 'still-pregnant');
    }

    /**
     * Record an "unable to contact" follow-up observation. Staff-only route.
     */
    public function recordUnableToContact(Request $request, $id)
    {
        return $this->recordFollowUp($request, $id, 'unable-to-contact');
    }

    private function recordFollowUp(Request $request, $id, string $observation)
    {
        $patient = Patient::findOrFail($id);

        try {
            if ($observation === 'still-pregnant') {
                $this->followUp->recordStillPregnant($patient, $request->user());
            } else {
                $this->followUp->recordUnableToContact($patient, $request->user());
            }
        } catch (DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()])->withInput();
        }

        $status = $observation === 'still-pregnant'
            ? PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED
            : PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT;

        $this->logAction(
            'UPDATE',
            'PATIENT',
            'Recorded follow-up observation for patient #' . $patient->id . ': ' . $status
        );

        return redirect()->route('pregnancy-outcomes.index')
            ->with('success', 'Follow-up observation recorded.');
    }

    /**
     * Build one monitoring row with friendly labels only — Blade never sees a
     * raw enum in the summary cells.
     *
     * @return array<string, mixed>
     */
    private function rowFor(Patient $patient): array
    {
        $state = $this->monitoring->deriveState($patient);
        $outcome = $patient->pregnancyOutcome;

        return [
            'patient' => $patient,
            'state' => $state,
            'state_label' => PregnancyOutcomeMonitoringService::stateLabel($state),
            'state_badge_class' => match ($state) {
                PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED => 'bg-amber-100 text-amber-900 ring-1 ring-amber-300',
                PregnancyOutcomeMonitoringService::STATE_STILL_PREGNANT_CONFIRMED => 'bg-green-100 text-green-800 ring-1 ring-green-300',
                PregnancyOutcomeMonitoringService::STATE_UNABLE_TO_CONTACT => 'bg-rose-100 text-rose-800 ring-1 ring-rose-300',
                PregnancyOutcomeMonitoringService::STATE_RESOLVED,
                PregnancyOutcomeMonitoringService::STATE_LEGACY_DELIVERED => 'bg-slate-100 text-slate-700',
                PregnancyOutcomeMonitoringService::STATE_LEGACY_REFERRED => 'bg-slate-100 text-slate-600',
                default => 'bg-slate-100 text-slate-700',
            },
            'status_label' => PregnancyOutcomeVocabulary::pregnancyStatusLabel($patient->status),
            'days_until_edd' => $this->monitoring->daysUntilOrPastEdd($patient),
            'follow_up_observable' => $this->monitoring->isFollowUpEligible($patient),
            'last_follow_up_at' => $outcome?->follow_up_recorded_at,
            'last_follow_up_label' => $outcome?->follow_up_status !== null
                ? PregnancyOutcomeVocabulary::followUpStatusLabel($outcome->follow_up_status)
                : null,
            'last_follow_up_by' => $outcome?->followUpRecordedBy?->name,
            'confirmed_at' => $outcome?->confirmed_at,
            'delivery_location_label' => $outcome?->delivery_location !== null
                ? PregnancyOutcomeVocabulary::deliveryLocationLabel($outcome->delivery_location)
                : null,
            'confirmation_source_label' => $outcome?->confirmation_source !== null
                ? PregnancyOutcomeVocabulary::confirmationSourceLabel($outcome->confirmation_source)
                : null,
        ];
    }
}