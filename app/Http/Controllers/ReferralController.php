<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Services\ReferralAnalyticsService;
use App\Services\ReferralAssessmentSnapshotService;
use App\Services\ReferralFollowThroughService;
use DomainException;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralAnalyticsService $referralAnalytics,
        private ReferralAssessmentSnapshotService $snapshotService,
        private ReferralFollowThroughService $followThrough
    ) {
    }

    /**
     * Normalize the month filter: 'all' or a missing/invalid value becomes
     * null (All Months); integers 1–12 are kept. Anything else defaults to
     * All Months so invalid input never reaches the analytics queries.
     */
    private function monthFilter($value): ?int
    {
        if ($value === 'all' || $value === null || $value === '') {
            return null;
        }

        $month = (int) $value;

        if ($month < 1 || $month > 12) {
            return null;
        }

        return $month;
    }

    /**
     * Show all referrals
     */
    public function index()
    {
        $query = Referral::with(['patient', 'prenatalVisit'])->latest();

        // Search by patient name
        if (request('search')) {
            $search = request('search');
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%");
            });
        }

        // Filter by status
        if (request('status') && request('status') !== 'all') {
            $query->where('status', request('status'));
        }

        $referrals = $query->paginate(15);

        // Stats
        $total = Referral::count();
        $pending = Referral::where('status', 'Pending')->count();
        $completed = Referral::where('status', 'Completed')->count();
        $refused = Referral::where('status', 'Refused')->count();
        $cancelled = Referral::where('status', 'Cancelled')->count();

        $analytics = $this->referralAnalytics->get($this->monthFilter(request('month')));

        return view('referrals.index', compact('referrals', 'total', 'pending', 'completed', 'refused', 'cancelled', 'analytics'));
    }

    /**
     * JSON analytics payload (aggregated totals only) for the month filter.
     */
    public function analytics(Request $request)
    {
        return response()->json($this->referralAnalytics->get($this->monthFilter($request->month)));
    }

    /**
     * Show create form
     *
     * Supports the assessment-linked mode through an optional
     * `prenatal_visit_id` query parameter. When present, the matching
     * PrenatalVisit must exist, belong to this patient, not be soft-deleted,
     * have `risk_level === 'HIGH'`, and carry structured persisted
     * `assessment_metadata` (a non-empty array representing the Sprint 13+
     * structured assessment). The immutable assessment snapshot and a
     * readable reason prefill are built from PERSISTED evidence only
     * (no assessment re-run). Without the parameter the form behaves as the
     * legacy manual referral flow.
     */
    public function create(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);

        // Block delivered patients
        if ($patient->status === 'DELIVERED') {
            return redirect()->back()
                ->with('error', 'Delivered patients cannot be referred.');
        }

        $prenatalVisitId = $request->query('prenatal_visit_id');

        $linkedVisit = null;
        $snapshot = null;
        $reasonPrefill = null;

        if ($prenatalVisitId) {
            $linkedVisit = PrenatalVisit::find((int) $prenatalVisitId);
            $metadata = is_array($linkedVisit?->assessment_metadata) ? $linkedVisit->assessment_metadata : [];

            if (! $linkedVisit || $linkedVisit->trashed()) {
                abort(404, 'The referenced prenatal assessment could not be found.');
            }

            if ($linkedVisit->patient_id !== $patient->id) {
                abort(403, 'This assessment does not belong to this patient.');
            }

            if ($linkedVisit->risk_level !== 'HIGH') {
                abort(403, 'Referrals may only be created from HIGH-risk assessments.');
            }

            if ($metadata === []) {
                abort(422, 'This historical assessment does not contain structured evidence for an assessment-linked referral. Use the manual referral workflow instead.');
            }

            $snapshot = $this->snapshotService->fromPrenatalVisit($linkedVisit);

            if (! is_array($snapshot)) {
                abort(422, 'This assessment is not eligible for a linked referral (no structured persisted evidence).');
            }

            $reasonPrefill = $this->snapshotService->prefillReason($snapshot);
        }

        return view('referrals.create', compact('patient', 'linkedVisit', 'snapshot', 'reasonPrefill'));
    }

    /**
     * Store new referral
     *
     * Two modes:
     *  - Assessment-linked: a `prenatal_visit_id` is supplied. The visit is
     *    reloaded at save time (TOCTOU protection), must belong to the
     *    submitted patient, must not be soft-deleted, must be risk level
     *    HIGH, must carry structured persisted `assessment_metadata`
     *    (non-empty array), must not already have a Pending referral, and
     *    the immutable `assessment_snapshot` is always rebuilt server-side —
     *    never read from the request.
     *  - Manual/legacy: no `prenatal_visit_id`; snapshot stays null.
     *
     * Delivered patients are rejected in both modes (the store() gap from
     * Phase 16A). Referral workflow state is fully decoupled from the
     * pregnancy lifecycle: creating a referral leaves `patient.status`
     * untouched (ONGOING stays ONGOING) and the referral row itself starts
     * as Pending (Phase 16D). The legacy `patient.status = REFERRED` write
     * was removed here.
     */
    public function store(Request $request)
    {
        $request->validate([
            'patient_id'        => 'required|exists:patients,id',
            'prenatal_visit_id' => 'nullable|integer',
            'referred_to'       => 'required|string|max:255',
            'doctor_name'       => 'nullable|string|max:255',
            'reason'            => 'required|string',
            'notes'             => 'nullable|string',
            'date_referred'     => 'required|date',
        ]);

        $patient = Patient::findOrFail($request->patient_id);

        // Delivered guard for both modes
        if ($patient->status === 'DELIVERED') {
            return redirect()->back()
                ->withInput()
                ->withErrors(['patient_id' => 'Delivered patients cannot be referred.']);
        }

        $prenatalVisitId = $request->input('prenatal_visit_id');
        $snapshot = null;

        if ($prenatalVisitId) {
            $linkedVisit = PrenatalVisit::withTrashed()->find((int) $prenatalVisitId);
            $metadata = is_array($linkedVisit?->assessment_metadata) ? $linkedVisit->assessment_metadata : [];

            if (! $linkedVisit || $linkedVisit->trashed()) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['prenatal_visit_id' => 'The referenced prenatal assessment is no longer available.']);
            }

            if ((int) $linkedVisit->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['prenatal_visit_id' => 'This assessment does not belong to the selected patient.']);
            }

            if ($linkedVisit->risk_level !== 'HIGH') {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['prenatal_visit_id' => 'Referrals may only be created from HIGH-risk assessments.']);
            }

            if ($metadata === []) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['prenatal_visit_id' => 'This historical assessment does not contain structured evidence for an assessment-linked referral. Use the manual referral workflow instead.']);
            }

            $snapshot = $this->snapshotService->fromPrenatalVisit($linkedVisit);

            if (! is_array($snapshot)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['prenatal_visit_id' => 'This assessment is not eligible for a linked referral (no structured persisted evidence).']);
            }

            $duplicate = Referral::where('prenatal_visit_id', $linkedVisit->id)
                ->where('status', 'Pending')
                ->exists();

            if ($duplicate) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['prenatal_visit_id' => 'A pending referral already exists for this assessment.']);
            }
        }

        $referral = Referral::create([
            'patient_id'          => $patient->id,
            'prenatal_visit_id'   => $prenatalVisitId ? (int) $prenatalVisitId : null,
            'assessment_snapshot' => $snapshot,
            'created_by'          => auth()->id(),
            'referred_to'         => $request->referred_to,
            'doctor_name'         => $request->doctor_name,
            'reason'              => $request->reason,
            'notes'               => $request->notes,
            'referral_date'       => $request->date_referred,
            'status'              => 'Pending',
        ]);

        // Phase 16D: the pregnancy lifecycle is intentionally left untouched.
        // The patient remains ONGOING; referral progress is tracked solely on
        // the Referral row (Pending -> Completed/Refused/Cancelled). The
        // legacy `patient.status = REFERRED` write was removed.

        // Audit log
        $description = $prenatalVisitId
            ? 'Created referral #' . $referral->id . ' for patient: ' . $patient->first_name . ' ' . $patient->last_name
                . ' linked to PrenatalVisit #' . $referral->prenatal_visit_id
            : 'Created referral for patient: ' . $patient->first_name . ' ' . $patient->last_name;

        $this->logAction(
            'CREATE',
            'REFERRAL',
            $description
        );
        return redirect()
            ->route('referrals.index')
            ->with('success', 'Referral created successfully.');
    }

    /**
     * Mark referral as completed (clinic-recorded follow-through).
     *
     * "Completed" means clinic staff recorded the referral follow-through as
     * completed/closed based on the info available to the clinic — NOT an
     * electronic acceptance, hospital admission, treatment completion, or
     * pregnancy end. Only Pending referrals may complete.
     */
    public function complete(Request $request, $id)
    {
        $referral = Referral::with('patient')->findOrFail($id);

        if ($referral->patient->status === 'DELIVERED') {
            return redirect()->back()->with('error', 'Delivered patients are read-only; referral status cannot be changed.');
        }

        try {
            $this->followThrough->complete($referral, auth()->user());
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Audit log
        $this->logAction(
            'UPDATE',
            'REFERRAL',
            'Completed referral #' . $referral->id . ' for patient: ' . $referral->patient->first_name . ' ' . $referral->patient->last_name
                . ' (Pending -> Completed)'
        );

        return redirect()->back()
            ->with('success', 'Referral marked as completed.');
    }

    /**
     * Record a patient refusal of a pending referral.
     *
     * Only Pending referrals may be refused. `refusal_notes` is required and
     * strongly validated; `waiver_signed` is a staff-entered boolean flag
     * documenting that a physical waiver was signed/recorded (documentation
     * only — no legal claims, no digital signatures, no uploads). The
     * server stamps `refusal_recorded_at` and `refusal_recorded_by`; the
     * browser can never forge them. `completed_at` stays null.
     */
    public function refuse(Request $request, $id)
    {
        $request->validate([
            'refusal_notes'  => 'required|string|min:10|max:2000',
            'waiver_signed'  => 'nullable|boolean',
        ]);

        $referral = Referral::with('patient')->findOrFail($id);

        if ($referral->patient->status === 'DELIVERED') {
            return redirect()->back()->with('error', 'Delivered patients are read-only; referral status cannot be changed.');
        }

        try {
            $this->followThrough->refuse(
                $referral,
                auth()->user(),
                $request->input('refusal_notes'),
                $request->boolean('waiver_signed')
            );
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Audit log
        $this->logAction(
            'UPDATE',
            'REFERRAL',
            'Recorded refusal for referral #' . $referral->id . ' for patient: ' . $referral->patient->first_name . ' ' . $referral->patient->last_name
                . ' (Pending -> Refused)' . ($referral->waiver_signed ? ' — physical waiver signed/recorded' : ' — no waiver signed')
        );

        return redirect()->back()
            ->with('success', 'Referral refusal recorded.');
    }

    /**
     * Cancel a pending referral (clinic-side decision).
     *
     * Distinct from a patient refusal. Closed referrals (Completed/Refused/
     * Cancelled) can never be cancelled again or reopened; a new referral row
     * preserves history. Original `referral.notes` are never overwritten and
     * no cancellation narrative is stored (the audit entry records the
     * transition).
     */
    public function cancel(Request $request, $id)
    {
        $referral = Referral::with('patient')->findOrFail($id);

        if ($referral->patient->status === 'DELIVERED') {
            return redirect()->back()->with('error', 'Delivered patients are read-only; referral status cannot be changed.');
        }

        try {
            $this->followThrough->cancel(
                $referral,
                auth()->user()
            );
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Audit log
        $this->logAction(
            'UPDATE',
            'REFERRAL',
            'Cancelled referral #' . $referral->id . ' for patient: ' . $referral->patient->first_name . ' ' . $referral->patient->last_name
                . ' (Pending -> Cancelled)'
        );

        return redirect()->back()
            ->with('success', 'Referral cancelled.');
    }

    /**
     * Print referral letter
     */
    public function print($id)
    {
        $referral = Referral::with('patient', 'user', 'refusalRecordedBy')->findOrFail($id);

        return view('referrals.print', compact('referral'));
    }

    /**
     * Referral detail page.
     *
     * Read-only view of a single referral built from PERSISTED data only:
     * the immutable `assessment_snapshot` (never the live visit) plus the
     * refreshed patient, creator and refusal recorder (both relationship
     * names render with a neutral fallback when the account was deleted).
     * No clinical logic is re-run and no historical snapshot is rewritten.
     */
    public function show($id)
    {
        $referral = Referral::with([
            'patient',
            'user',
            'refusalRecordedBy',
            'prenatalVisit',
        ])->findOrFail($id);

        return view('referrals.show', compact('referral'));
    }
}
