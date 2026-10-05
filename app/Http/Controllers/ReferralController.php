<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Services\ReferralAnalyticsService;
use App\Services\ReferralAssessmentSnapshotService;
use App\Services\ReferralFollowThroughService;
use App\Services\SystemNotificationService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralAnalyticsService $referralAnalytics,
        private ReferralAssessmentSnapshotService $snapshotService,
        private ReferralFollowThroughService $followThrough,
        private SystemNotificationService $notifications
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
 * Normalize the analytics year filter.
 *
 * A missing or invalid year defaults to the current year.
 * Years are limited to a reasonable range so arbitrary values
 * cannot be passed into the analytics queries.
 */
private function yearFilter($value): int
{
    $currentYear = (int) now()->year;

    if ($value === null || $value === '') {
        return $currentYear;
    }

    $year = filter_var($value, FILTER_VALIDATE_INT);

    if ($year === false || $year < 2000 || $year > $currentYear) {
        return $currentYear;
    }

    return $year;
}

    /**
     * Show all referrals — one row per patient.
     *
     * Each row is a patient whose latest non-archived referral supplies the
     * visible status/destination/date/source, ordered by that referral
     * (referral_date desc, id desc — same order as the print history). The
     * expandable history section renders every non-archived referral of that
     * patient (newest first); archived referrals stay hidden everywhere by
     * the SoftDeletes global scope. Summary counters keep counting referral
     * rows (archived excluded), so cards and list are intentionally
     * row-based vs patient-based.
     */
    public function index()
    {
        $query = Patient::query()
            ->whereHas('latestReferral')
            ->with([
                'latestReferral.prenatalVisit',
                'referrals' => fn ($query) => $query->orderByDesc('referral_date')->orderByDesc('id'),
                'referrals.prenatalVisit',
            ])
            ->withAggregate(['latestReferral as latest_referral_date'], 'referral_date', 'max')
            ->withAggregate(['latestReferral as latest_referral_id'], 'id', 'max');

        // Search by patient name (a patient appears at most once).
        if (request('search')) {
            $search = request('search');
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by the latest (non-archived) referral status.
        if (request('status') && request('status') !== 'all') {
            $query->whereHas('latestReferral', function ($query) {
                $query->where('status', request('status'));
            });
        }

        $referrals = $query
            ->orderByDesc('latest_referral_date')
            ->orderByDesc('latest_referral_id')
            ->paginate(15)
            ->withQueryString();

        // Stats (referral rows; archived excluded by SoftDeletes)
        $total = Referral::count();
        $pending = Referral::where('status', 'Pending')->count();
        $completed = Referral::where('status', 'Completed')->count();
        $refused = Referral::where('status', 'Refused')->count();
        $cancelled = Referral::where('status', 'Cancelled')->count();

        $analyticsYear = $this->yearFilter(request('year'));
        $analyticsMonth = $this->monthFilter(request('month'));

        $analytics = $this->referralAnalytics->get(
        $analyticsYear,
        $analyticsMonth
);

        return view('referrals.index', compact('referrals', 'total', 'pending', 'completed', 'refused', 'cancelled', 'analytics'));
    }

    /**
     * Select a referable pregnancy before opening the existing referral form.
     *
     * Eligibility = clinical gate (latest assessment HIGH) PLUS the
     * referral-lifecycle rule, applied to ONGOING and REFERRED patients
     * alike so neither status can bypass it:
     *
     *  - no non-archived referral yet  -> eligible (first referral)
     *  - latest non-archived referral = Cancelled -> eligible again
     *  - latest = Pending / Completed / Refused   -> excluded
     *
     * DELIVERED patients are excluded via the status filter. The legacy
     * `patient.status` value itself is never rewritten. create() and
     * store() enforce the same lifecycle rule server-side, so direct URL
     * or direct POST manipulation cannot bypass it.
     */
    public function selectPatient(Request $request)
{
    $validated = $request->validate([
        'search' => 'nullable|string|max:255',
    ]);

    $search = trim($validated['search'] ?? '');

    $patients = Patient::query()
        ->whereIn('status', ['ONGOING', 'REFERRED'])

        // Referral-lifecycle rule (any pregnancy status): eligible only
        // with no non-archived referral yet, or when the latest
        // non-archived referral was Cancelled. Pending/Completed/Refused
        // latest referrals exclude the patient; archived rows do not count
        // (the relation only spans non-archived referrals).
        ->where(function ($query) {
            $query->whereDoesntHave('latestReferral')
                ->orWhereHas('latestReferral', function ($query) {
                    $query->where('status', 'Cancelled');
                });
        })

        // Only show patients whose LATEST assessment is HIGH.
        ->whereHas('latestPrenatalAssessment', function ($query) {
            $query->where('risk_level', 'HIGH');
        })

        // Load that same latest assessment for the Blade view.
        ->with('latestPrenatalAssessment')

        ->when($search !== '', function ($query) use ($search) {
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        })

        ->orderBy('last_name')
        ->orderBy('first_name')
        ->orderBy('id')
        ->paginate(15)
        ->withQueryString();

    return view(
        'referrals.select-patient',
        compact('patients', 'search')
    );
}

    /**
 * JSON analytics payload for the selected year and optional month.
 */
public function analytics(Request $request)
{
    $year = $this->yearFilter($request->query('year'));
    $month = $this->monthFilter($request->query('month'));

    return response()->json(
        $this->referralAnalytics->get(
            $year,
            $month
        )
    );
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
     * legacy manual referral flow. Both modes additionally enforce the
     * referral-lifecycle rule: no non-archived referral yet, or a Cancelled
     * latest one (Pending/Completed/Refused are refused with a redirect).
     */
    public function create(Request $request, $id)
    {
        $patient = Patient::findOrFail($id);

        // Block delivered patients
        if ($patient->status === 'DELIVERED') {
            return redirect()->back()
                ->with('error', 'Delivered patients cannot be referred.');
        }

        // Referral-lifecycle rule (mirrors selectPatient()/store()): the
        // form itself is refused when the latest non-archived referral is
        // Pending, Completed or Refused, so a direct URL visit cannot
        // bypass the selector. Cancelled or no referral stays allowed.
        $latestReferral = $patient->latestReferral;

        if ($latestReferral && $latestReferral->status !== 'Cancelled') {
            return redirect()->back()
                ->with('error', 'This patient\'s latest referral is ' . $latestReferral->status . '. Only patients with no referral or a cancelled referral can be referred.');
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
     *  - Manual/legacy: no `prenatal_visit_id`; snapshot stays null. A
     *    second Pending referral for the same patient is rejected while a
     *    Pending one exists. Regardless of mode, the referral-lifecycle
     *    guard below additionally requires no prior non-archived referral
     *    or a Cancelled latest one (Completed/Refused rejected).
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

        // Manual mode: one active (Pending) referral per patient. A
        // follow-up after a Cancelled referral is always a new row (the
        // closed row stays untouched as history); Completed/Refused latest
        // rows are rejected by the lifecycle guard below. Archived rows
        // are excluded by the SoftDeletes scope. The assessment-linked
        // mode keeps its existing per-assessment pending rule above.
        if (! $prenatalVisitId) {
            $duplicatePending = Referral::where('patient_id', $patient->id)
                ->where('status', 'Pending')
                ->exists();

            if ($duplicatePending) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['patient_id' => 'A pending referral already exists for this patient.']);
            }
        }

        // Referral-lifecycle rule (both modes, mirrors selectPatient() and
        // create()): a new referral row may only be created when the patient
        // has no non-archived referral yet or the latest one is Cancelled.
        // Pending is already rejected by the mode-specific duplicate guards
        // above for manual and same-visit linked attempts; this final guard
        // additionally rejects Pending/Completed/Refused latest rows so a
        // direct POST cannot bypass the selector. The previous Cancelled row
        // is never touched — creation always inserts a brand-new referral
        // with its own id.
        $latestReferral = $patient->latestReferral;

        if ($latestReferral && $latestReferral->status !== 'Cancelled') {
            return redirect()->back()
                ->withInput()
                ->withErrors(['patient_id' => 'This patient\'s latest referral is ' . $latestReferral->status . '. Only patients with no referral or a cancelled referral can be referred.']);
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

        // Notify all active clinic users (admins + staff) that a new pending
        // referral requires follow-through - single-clinic recipient model.
        $this->notifications->notifyReferralCreated($referral);

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

        $this->notifications->notifyReferralClosed($referral);

        return redirect()->back()
            ->with('success', 'Referral marked as completed.');
    }

    /** 
     * Record a patient refusal of a pending referral.
     *
     * Only Pending referrals may be refused. `refusal_notes` is required and
     * strongly validated; `waiver_signed` is a staff-entered boolean flag
     * documenting that a physical waiver was signed/recorded (documentation
     * only — no legal claims, no digital signatures). An OPTIONAL supporting
     * image (`refusal_image`) may be attached as evidence: it is validated
     * server-side (image, JPG/JPEG/PNG/WebP, max 5 MB), stored on the public
     * disk under a SERVER-GENERATED name in `referrals/`, and only its
     * relative path is persisted on this exact referral row. The server
     * stamps `refusal_recorded_at` and `refusal_recorded_by`; the browser
     * can never forge them. `completed_at` stays null. The image is stored
     * before the refusal operation runs, so if that operation then fails for
     * ANY reason (DomainException from the lifecycle guard or an unexpected
     * Throwable such as a QueryException during the database save), the
     * newly stored file is deleted before the failure is handled/rethrown -
     * only the file created by THIS request is ever removed, and files that
     * already belong to a recorded refusal are never touched.
     */
    public function refuse(Request $request, $id)
    {
        $request->validate([
            'refusal_notes'  => 'required|string|min:10|max:2000',
            'waiver_signed'  => 'nullable|boolean',
            'refusal_image'  => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'refusal_image.max'   => 'The supporting image must not exceed 5MB.',
            'refusal_image.mimes' => 'Please upload a JPG, JPEG, PNG, or WebP image.',
            'refusal_image.image' => 'Please upload a JPG, JPEG, PNG, or WebP image.',
        ]);

        $referral = Referral::with('patient')->findOrFail($id);

        if ($referral->patient->status === 'DELIVERED') {
            return redirect()->back()->with('error', 'Delivered patients are read-only; referral status cannot be changed.');
        }

        // Store the optional supporting image BEFORE the transition. The
        // directory and filename are generated server-side: the request
        // never controls the path and the original filename is never used.
        // $storedImagePath is set ONLY by this request's storeAs() call, so
        // it is the sole path cleanup is ever allowed to delete.
        $storedImagePath = null;

        if ($request->hasFile('refusal_image')) {
            $file = $request->file('refusal_image');
            $fileName = 'refusal_' . $referral->id . '_' . time() . '_' . Str::random(8)
                . '.' . ($file->guessExtension() ?: 'jpg');

            $storedPath = $file->storeAs('referrals', $fileName, 'public');

            if ($storedPath === false) {
                return redirect()->back()->with('error', 'The supporting image could not be stored. Please try again.');
            }

            $storedImagePath = $storedPath;
        }

        try {
            $this->followThrough->refuse(
                $referral,
                auth()->user(),
                $request->input('refusal_notes'),
                $request->boolean('waiver_signed'),
                $storedImagePath
            );
        } catch (DomainException $e) {
            // Existing user-facing behavior: lifecycle guard failures show
            // their message as an error flash. The refusal was never
            // recorded, so the file newly stored by this request is removed
            // (no orphan). Files belonging to other records are never touched.
            $this->deleteStoredRefusalImage($storedImagePath);

            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Unexpected failure after the file was stored (e.g. a
            // QueryException while persisting refusal_image_path): remove
            // ONLY this request's newly stored file, then rethrow so the
            // original error is never swallowed or replaced.
            $this->deleteStoredRefusalImage($storedImagePath);

            throw $e;
        }

        // Audit log
        $this->logAction(
            'UPDATE',
            'REFERRAL',
            'Recorded refusal for referral #' . $referral->id . ' for patient: ' . $referral->patient->first_name . ' ' . $referral->patient->last_name
                . ' (Pending -> Refused)' . ($referral->waiver_signed ? ' — physical waiver signed/recorded' : ' — no waiver signed')
                . ($storedImagePath !== null ? ' — supporting image attached' : '')
        );

        $this->notifications->notifyReferralClosed($referral);

        return redirect()->back()
            ->with('success', 'Referral refusal recorded.');
    }

    /**
     * Delete ONLY a supporting image newly stored by the current refusal
     * request. Defensive guards: the path must be non-null and must live
     * under this feature's `referrals/` folder, so an unrelated or already
     * persisted file can never be removed by mistake. Runs only when the
     * refusal was NOT recorded (transaction rolled back / guard rejected),
     * meaning the database never references the deleted path.
     */
    private function deleteStoredRefusalImage(?string $path): void
    {
        if ($path === null || !Str::startsWith($path, 'referrals/')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
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

        $this->notifications->notifyReferralClosed($referral);

        return redirect()->back()
            ->with('success', 'Referral cancelled.');
    }

    /**
     * Print referral letter
     */
    public function print($id)
    {
        $referral = Referral::with('patient', 'user', 'refusalRecordedBy')->findOrFail($id);

        $referralHistory = Referral::with([
            'patient', 'user', 'refusalRecordedBy',
            'prenatalVisit' => fn ($query) => $query->withTrashed(),
        ])
            ->where('patient_id', $referral->patient_id)
            ->orderByDesc('referral_date')
            ->orderByDesc('id')
            ->get();

        return view('referrals.print', compact('referral', 'referralHistory'));
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

    /**
     * Stream the optional supporting image stored with a recorded refusal.
     *
     * The path always comes from the persisted referral row — never from the
     * request — and mirrors the UltrasoundController::file() pattern: the
     * route sits inside the authenticated no-store group, and a missing
     * file resolves to 404 just like the detail page of an archived row
     * (route-model binding applies the SoftDeletes scope automatically).
     */
    public function refusalImage(Referral $referral)
    {
        $path = $referral->refusal_image_path;

        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    /**
     * Archive a referral (soft delete).
     *
     * The row is never physically removed: `delete()` only stamps
     * `deleted_at`, which hides the referral from every normal Eloquent
     * query (active listing, summary counters, follow-through) while the
     * Archived Referrals page can still surface and restore it.
     */
    public function destroy($id)
    {
        $referral = Referral::findOrFail($id);

        $referral->delete();

        $this->logAction(
            'ARCHIVE',
            'REFERRAL',
            'Archived referral #' . $referral->id . ' for patient ID: ' . $referral->patient_id
        );

        return redirect()->route('referrals.index')
            ->with('success', 'Referral archived successfully.');
    }

    /**
     * List archived referrals (soft-deleted only), newest archived first.
     */
    public function archived()
    {
        $referrals = Referral::onlyTrashed()
            ->with(['patient', 'prenatalVisit'])
            ->whereHas('patient')
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->get();

        return view('referrals.archived', compact('referrals'));
    }

    /**
     * Restore an archived referral back to the active Referrals listing.
     */
    public function restoreArchived($id)
    {
        $referral = Referral::onlyTrashed()
            ->whereHas('patient')
            ->findOrFail($id);

        $referral->restore();

        return redirect()->route('referrals.index')
            ->with('success', 'Referral restored successfully.');
    }
}
