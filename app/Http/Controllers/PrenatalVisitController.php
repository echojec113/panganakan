<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrenatalVisit;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\PrenatalVisitReminderMail;
use App\Mail\PrenatalVisitScheduleUpdatedMail;
use App\Services\BloodPressureAssessmentService;
use App\Services\AssessmentContextBuilder;
use App\Services\AssessmentMetadataSerializer;
use App\Services\MedicalHistoryConditionSyncService;
use App\Services\PatientAssessmentRecalculationService;
use App\Services\PrenatalFollowUpAppointmentService;
use App\Services\RiskAssessmentService;
use App\Services\SystemNotificationService;
use App\Services\GestationalAgeCalculator;
use App\ValueObjects\AssessmentContext;
use DomainException;

class PrenatalVisitController extends Controller
{
    private RiskAssessmentService $riskAssessmentService;
    private PatientAssessmentRecalculationService $recalculationService;
    private MedicalHistoryConditionSyncService $medicalHistorySyncService;
    private AssessmentMetadataSerializer $metadataSerializer;
    private AssessmentContextBuilder $contextBuilder;
    private SystemNotificationService $notifications;
    private GestationalAgeCalculator $gestationalAgeCalculator;
    private PrenatalFollowUpAppointmentService $followUps;

    public function __construct(
        RiskAssessmentService $riskAssessmentService,
        PatientAssessmentRecalculationService $recalculationService,
        MedicalHistoryConditionSyncService $medicalHistorySyncService,
        AssessmentMetadataSerializer $metadataSerializer,
        AssessmentContextBuilder $contextBuilder,
        SystemNotificationService $notifications,
        GestationalAgeCalculator $gestationalAgeCalculator,
        PrenatalFollowUpAppointmentService $followUps
    ) {
        $this->riskAssessmentService = $riskAssessmentService;
        $this->recalculationService = $recalculationService;
        $this->medicalHistorySyncService = $medicalHistorySyncService;
        $this->metadataSerializer = $metadataSerializer;
        $this->contextBuilder = $contextBuilder;
        $this->notifications = $notifications;
        $this->gestationalAgeCalculator = $gestationalAgeCalculator;
        $this->followUps = $followUps;
    }


    public function index(Request $request)
{
    /*
    |--------------------------------------------------------------------------
    | Prenatal Visits Listing
    |--------------------------------------------------------------------------
    |
    | Active Prenatal Visits only show records belonging to patients whose
    | pregnancy status is ONGOING.
    |
    | Delivered patients keep their prenatal records in the database and can
    | still be accessed through their patient history, but they are excluded
    | from this active prenatal-workflow page.
    |
    */

    $search = trim((string) $request->query('search', ''));
    $risk = strtoupper(trim((string) $request->query('risk', '')));

    $allowedRisks = [
        'HIGH',
        'LOW',
        'ASSESSMENT INCOMPLETE',
        'PENDING',
    ];

    // Ignore invalid risk values instead of allowing arbitrary filter input.
    if ($risk !== '' && !in_array($risk, $allowedRisks, true)) {
        $risk = '';
    }

    /*
    |--------------------------------------------------------------------------
    | Base Scope
    |--------------------------------------------------------------------------
    */

    $baseQuery = PrenatalVisit::query()
        ->whereHas('patient', function ($query) {
            $query->where('status', 'ONGOING');
        });

    /*
    |--------------------------------------------------------------------------
    | Summary Cards
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | These are calculated separately from pagination.
    | Therefore "Total Visits" will NOT suddenly become 10 just because the
    | table displays 10 records per page.
    |
    */

    $totalVisits = (clone $baseQuery)->count();

    // Count distinct patients whose CURRENT/LATEST prenatal assessment is HIGH.
    // This avoids counting one high-risk patient multiple times simply because
    // she has multiple prenatal visits.
    $latestAssessmentIds = PrenatalVisit::latestAssessmentIds();

    $highRiskPatients = PrenatalVisit::query()
        ->whereIn('id', $latestAssessmentIds)
        ->where('risk_level', 'HIGH')
        ->whereHas('patient', function ($query) {
            $query->where('status', 'ONGOING');
        })
        ->distinct('patient_id')
        ->count('patient_id');

    // Follow-ups that are already due today or overdue.
    // Only the latest prenatal visit of each ongoing patient is considered,
    // otherwise an old visit could incorrectly remain "due" after a newer
    // follow-up visit was already recorded.
    $followUpsDue = PrenatalVisit::query()
        ->whereIn('id', $latestAssessmentIds)
        ->whereHas('patient', function ($query) {
            $query->where('status', 'ONGOING');
        })
        ->whereNotNull('next_visit_date')
        ->whereDate('next_visit_date', '<=', today())
        ->count();

    $visitsThisMonth = (clone $baseQuery)
        ->whereBetween('visit_date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ])
        ->count();

    /*
    |--------------------------------------------------------------------------
    | Table Query
    |--------------------------------------------------------------------------
    */

    $query = PrenatalVisit::query()
        ->with('patient')
        ->whereIn('id', PrenatalVisit::latestAssessmentIds())
        ->whereHas('patient', function ($patientQuery) {
            $patientQuery->where('status', 'ONGOING');
        });

    /*
    |--------------------------------------------------------------------------
    | Live Search
    |--------------------------------------------------------------------------
    |
    | Supports:
    |   Jerich
    |   Jerich Dela Cruz
    |   PT-0017
    |   PT0017
    |   17
    |
    | We deliberately do NOT search contact numbers here.
    |
    */

    if ($search !== '') {
        $patientIdSearch = strtoupper($search);

        // PT-0017 -> 0017
        // PT0017  -> 0017
        $patientIdSearch = preg_replace('/^PT-?/', '', $patientIdSearch);

        $query->whereHas('patient', function ($patientQuery) use ($search, $patientIdSearch) {
            $patientQuery->where(function ($searchQuery) use ($search, $patientIdSearch) {
                $searchQuery
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");

                /*
                 * Search the numeric database ID only when the Patient ID
                 * entered by the user resolves to a valid number.
                 */
                if (ctype_digit($patientIdSearch)) {
                    $searchQuery->orWhere('id', (int) $patientIdSearch);
                }
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Assessment Filter
    |--------------------------------------------------------------------------
    */

    if ($risk !== '') {
        $query->where('risk_level', $risk);
    }

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | 10 visits per page.
    | withQueryString() preserves search/risk when moving between pages.
    |
    */

    $visits = $query
        ->orderByDesc('visit_date')
        ->orderByDesc('id')
        ->paginate(10)
        ->withQueryString();

    return view('prenatal_visits.index', compact(
        'visits',
        'search',
        'risk',
        'totalVisits',
        'highRiskPatients',
        'followUpsDue',
        'visitsThisMonth'
    ));
}

    public function archived()
    {
        $visits = PrenatalVisit::onlyTrashed()
            ->with('patient')
            ->whereHas('patient')
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->get();

        return view('prenatal_visits.archived', compact('visits'));
    }

    public function restoreArchived($id)
    {
        $visit = PrenatalVisit::onlyTrashed()
            ->whereHas('patient')
            ->findOrFail($id);

        $visit->restore();

        return redirect()->route('prenatal-visits.index')
            ->with('success', 'Prenatal visit restored successfully.');
    }

    public function create(Request $request)
    {
        if ($request->has('patient_id')) {
            $patient = Patient::findOrFail($request->patient_id);

            session([
                'prenatal_visit_locked_patient_id' => $patient->id,
            ]);

            $sourcePreview = $this->sourcePreview($this->contextBuilder->buildForPatient($patient, null, []));
            $referenceDate = $request->old('visit_date', today()->toDateString());
            $expectedGestationalAge = $this->calculateInitialGestationalAge($patient, $referenceDate);
            $gestationalAgeHint = $this->gestationalAgeHint($patient, $referenceDate, $expectedGestationalAge);

            return view('prenatal_visits.create', [
                'lockedPatient' => $patient,
                'sourcePreview' => $sourcePreview,
                'expectedGestationalAge' => $expectedGestationalAge,
                'gestationalAgeHint' => $gestationalAgeHint,
                'hasOldGestationalAge' => $request->session()->hasOldInput('gestational_age'),
            ]);
        }

        session()->forget('prenatal_visit_locked_patient_id');

        $patients = Patient::all();
        $selectedPatient = $request->old('patient_id')
            ? Patient::find($request->old('patient_id'))
            : null;
        $referenceDate = $request->old('visit_date', today()->toDateString());
        $expectedGestationalAge = $this->calculateInitialGestationalAge($selectedPatient, $referenceDate);
        $gestationalAgeHint = $this->gestationalAgeHint($selectedPatient, $referenceDate, $expectedGestationalAge);

        return view('prenatal_visits.create', [
            'patients' => $patients,
            'lockedPatient' => null,
            'selectedPatient' => $request->old('patient_id'),
            'expectedGestationalAge' => $expectedGestationalAge,
            'gestationalAgeHint' => $gestationalAgeHint,
            'hasOldGestationalAge' => $request->session()->hasOldInput('gestational_age'),
        ]);
    }

    public function store(Request $request)
    {
        $lockedPatientId = session('prenatal_visit_locked_patient_id');

        if ($lockedPatientId && (int) $request->patient_id !== (int) $lockedPatientId) {
            return back()->withErrors([
                'patient_id' => 'Patient mismatch. Please reopen Add Prenatal Visit from the patient\'s profile.',
            ])->withInput();
        }

        // ======================
        // ENHANCED VALIDATION
        // ======================
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'visit_date' => 'required|date|before_or_equal:today',

            // Vital Signs
            'bp_sys' => 'required|numeric|min:60|max:480',
            'bp_dia' => 'required|numeric|min:40|max:350',
            'weight' => 'required|numeric|min:30|max:250|regex:/^\d+(\.\d)?$/',
            'temperature' => 'nullable|numeric|min:35|max:40',

            // Repeat BP (optional, both-or-neither)
            'repeat_bp_sys' => 'nullable|required_with:repeat_bp_dia|numeric|min:60|max:480',
            'repeat_bp_dia' => 'nullable|required_with:repeat_bp_sys|numeric|min:40|max:350',
            'bp_verification_status' => 'nullable|string|in:UNABLE_TO_REPEAT',
            'bp_verification_note' => 'nullable|string|max:500',

            // Pregnancy Monitoring
            'gestational_age' => 'required|numeric|min:4|max:42',
            'fundic_height' => 'nullable|string|max:50',
            'fetal_heart_tone' => 'nullable|string|max:50',
            'fetal_movement' => 'nullable|string|max:50',

            // Leopold's Maneuver
            'presenting_part' => 'nullable|string|max:100',
            'uterine_activity' => 'nullable|string|max:100',

            // Pelvic Examination
            'cervical_dilation' => 'nullable|numeric|min:0|max:10',
            'bag_of_water' => 'nullable|string|max:50',

            // Risk Factors
            'hypertension' => 'required|boolean',
            'diabetes' => 'required|boolean',
            'anemia' => 'required|boolean',

            // Doctor Assessment
            'treatment_plan' => 'nullable|string',
            'next_visit_date' => 'nullable|date|after_or_equal:today',
            'next_visit_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string'
        ], [
            // Custom error messages
            'bp_sys.min' => 'Systolic BP must be at least 60 mmHg',
            'bp_sys.max' => 'Systolic BP cannot exceed 480 mmHg',
            'bp_dia.min' => 'Diastolic BP must be at least 40 mmHg',
            'bp_dia.max' => 'Diastolic BP cannot exceed 350 mmHg',
            'weight.min' => 'Weight must be at least 30 kg',
            'weight.max' => 'Weight cannot exceed 250 kg',
            'weight.regex' => 'Weight must have at most 1 decimal place',
            'temperature.min' => 'Temperature must be at least 35Â°C',
            'temperature.max' => 'Temperature cannot exceed 40Â°C',
            'gestational_age.min' => 'Gestational age must be at least 4 weeks',
            'gestational_age.max' => 'Gestational age cannot exceed 42 weeks',
            'cervical_dilation.min' => 'Cervical dilation cannot be negative',
            'cervical_dilation.max' => 'Cervical dilation cannot exceed 10 cm',
            'visit_date.before_or_equal' => 'Visit date cannot be in the future',
            'next_visit_date.after_or_equal' => 'Next visit date must be today or in the future',
            'next_visit_time.date_format' => 'Next visit time must be a valid clock time (HH:MM).',
            'patient_id.exists' => 'Selected patient does not exist',
            'repeat_bp_sys.required_with' => 'Repeat BP systolic is required when repeat diastolic is provided',
            'repeat_bp_dia.required_with' => 'Repeat BP diastolic is required when repeat systolic is provided',
        ]);

        // â•â•â• Phase 3 - canonical H:i (and null when absent). A MySQL
        // `time` column returns "H:i:s" while the form and the slot grid
        // speak "H:i", so normalising ONCE here keeps the stored value,
        // the change detector and the picker's preselection in one shape.
        $nextVisitTime = \App\Services\PrenatalFollowUpAppointmentService::canonicalTime($request->input('next_visit_time'));

        // â•â•â• Phase 3 - Next Visit time without a date would be an
        // unschedulable half-schedule. Refused BEFORE any risk work so the
        // staff can fix it in place (the slot picker only renders once a
        // date exists, so this can only arrive from a stale/forged form).
        if ($nextVisitTime !== null && ! $request->filled('next_visit_date')) {
            return back()->withErrors([
                'next_visit_time' => 'Select a Next Visit Date before choosing a Next Visit Time.',
            ])->withInput();
        }

        // Read-only slot pre-check: reports a field error before the visit
        // transaction starts. sync() re-runs the SAME checks authoritatively
        // under lock, so a race between here and the write is still caught.
        try {
            $this->followUps->assertScheduleUsable(
                $request->input('next_visit_date'),
                $nextVisitTime
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'next_visit_time' => $exception->getMessage(),
            ])->withInput();
        }

        // ======================
        // LOGICAL VALIDATIONS
        // ======================

        // BP Logic: Systolic should be greater than Diastolic
        if ($request->bp_sys <= $request->bp_dia) {
            return back()->withErrors([
                'bp_sys' => 'Systolic BP must be greater than Diastolic BP',
                'bp_dia' => 'Systolic BP must be greater than Diastolic BP'
            ])->withInput();
        }

        // Repeat BP Logic: Systolic should be greater than Diastolic
        if ($request->filled('repeat_bp_sys') && $request->filled('repeat_bp_dia') && $request->repeat_bp_sys <= $request->repeat_bp_dia) {
            return back()->withErrors([
                'repeat_bp_sys' => 'Repeat systolic BP must be greater than repeat diastolic BP',
                'repeat_bp_dia' => 'Repeat systolic BP must be greater than repeat diastolic BP'
            ])->withInput();
        }

        // UNABLE_TO_REPEAT requires a non-empty verification note
        if ($request->bp_verification_status === 'UNABLE_TO_REPEAT' && trim((string) $request->bp_verification_note) === '') {
            return back()->withErrors([
                'bp_verification_note' => 'A verification note is required when the status is "Unable to Repeat".',
            ])->withInput();
        }

        // Gestational age vs LMP validation
        $patient = Patient::find($request->patient_id);
        if ($patient && $patient->lmp) {
            $expectedWeeks = $this->gestationalAgeCalculator->calculate(
                $patient->lmp->toDateString(),
                $request->visit_date
            );

            if ($expectedWeeks === null) {
                return back()->withErrors([
                    'gestational_age' => 'Visit date cannot be before the patient\'s LMP.',
                ])->withInput();
            }

            if (abs($expectedWeeks - (float) $request->gestational_age) > 3) {
                return back()->withErrors([
                    'gestational_age' => "Gestational age doesn't match LMP date. Based on LMP ({$patient->lmp}), expected GA is {$expectedWeeks} weeks (Â±3 weeks allowed)."
                ])->withInput();
            }
        }

        // Build repeat BP inputs array
        $repeatBpInputs = null;
        if ($request->filled('repeat_bp_sys') && $request->filled('repeat_bp_dia')) {
            $repeatBpInputs = [
                'bp_sys' => (int) $request->repeat_bp_sys,
                'bp_dia' => (int) $request->repeat_bp_dia,
            ];
        }

        // ======================
        // ASSESS RISK (pure computation, before persistence)
        // ======================
        $riskAssessment = $this->riskAssessmentService->assess(
            $patient,
            $request->only([
                'bp_sys', 'bp_dia', 'weight', 'gestational_age', 'hypertension', 'diabetes', 'anemia'
            ]),
            $repeatBpInputs,
            $request->bp_verification_status,
            $request->bp_verification_note,
            $request->visit_date
        );

        $risk = $riskAssessment['risk_level'];
        $assessment = $riskAssessment['assessment'];
        $recommendation = $riskAssessment['recommendation'];
        $reasons = $riskAssessment['reasons'];
        $nextVisit = $riskAssessment['nextVisit'];

        $finalNextVisit = $request->next_visit_date ?: $nextVisit->toDateString();

        // Snapshot the patient's EFFECTIVE risk before this visit exists so a
        // real non-HIGH -> HIGH transition can be detected exactly once after
        // the write (SystemNotificationService::notifyHighRiskTransition).
        $previousEffectiveRisk = $this->notifications->effectiveRiskLevel((int) $patient->id);

        // ======================
        // CREATE VISIT + RISK FIELDS IN ONE TRANSACTION
        // ======================
        //
        // Phase 3: the staff-confirmed follow-up appointment is created
        // INSIDE this same transaction (after the visit exists, so the
        // linkage has a real prenatal_visits id). A slot that is no longer
        // available therefore rolls back the WHOLE save - never a visit
        // without its appointment or an appointment without its visit - and
        // surfaces as a next_visit_time field error below, never a 500.
        $followUpResult = ['appointment' => null, 'event' => 'none'];

        try {
            $visit = DB::transaction(function () use (
                $request,
                $patient,
                $repeatBpInputs,
                $riskAssessment,
                $risk,
                $assessment,
                $recommendation,
                $reasons,
                $finalNextVisit,
                $nextVisitTime,
                &$followUpResult
            ) {
            $visit = PrenatalVisit::create([
                'patient_id' => $request->patient_id,
                'visit_date' => $request->visit_date,
                'bp_sys' => $request->bp_sys,
                'bp_dia' => $request->bp_dia,
                'weight' => $request->weight,
                'gestational_age' => $request->gestational_age,
                'hypertension' => $request->hypertension,
                'diabetes' => $request->diabetes,
                'anemia' => $request->anemia,
                'risk_level' => 'ASSESSMENT INCOMPLETE',
                'risk_reasons' => [],
                'assessment' => 'Pending',
                'recommendation' => 'Pending',
                'next_visit_time' => \App\Services\PrenatalFollowUpAppointmentService::canonicalTime($request->next_visit_time),
                'treatment_plan' => $request->treatment_plan,
                'temperature' => $request->temperature,
                'fundic_height' => $request->fundic_height,
                'fetal_heart_tone' => $request->fetal_heart_tone,
                'fetal_movement' => $request->fetal_movement,
                'presenting_part' => $request->presenting_part,
                'uterine_activity' => $request->uterine_activity,
                'cervical_dilation' => $request->cervical_dilation,
                'bag_of_water' => $request->bag_of_water,
                'repeat_bp_sys' => $repeatBpInputs['bp_sys'] ?? null,
                'repeat_bp_dia' => $repeatBpInputs['bp_dia'] ?? null,
                'repeat_bp_recorded_at' => $repeatBpInputs ? now() : null,
                'repeat_bp_recorded_by' => $repeatBpInputs ? auth()->id() : null,
                'bp_verification_status' => null,
            ]);

            $visit->update([
                'risk_level' => $risk,
                'risk_reasons' => $reasons,
                'decision_source' => $riskAssessment['decision_source'] ?? null,
                'missing_records' => $riskAssessment['missing_records'] ?? [],
                'rule_reasons' => $riskAssessment['rule_reasons'] ?? [],
                'ml_prediction' => $riskAssessment['ml_prediction'] ?? null,
                'ml_valid' => $riskAssessment['ml_valid'] ?? false,
                'assessment' => $assessment,
                'recommendation' => $recommendation,
                'next_visit_date' => $finalNextVisit,
                'next_visit_time' => $request->next_visit_time,
                'urgency' => $riskAssessment['urgency'] ?? null,
                'bp_assessment' => $riskAssessment['bp_assessment'] ?? null,
                'factor_evidence' => $riskAssessment['factor_evidence'] ?? [],
                'assessment_metadata' => $this->metadataSerializer->fromResult($riskAssessment, $visit),
                'bp_verification_status' => $riskAssessment['bp_assessment']['verification_status'] ?? BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED,
            ]);

            // One-way sync of confirmed visit diabetes/anemia into Medical History.
            // Runs only after the visit has been persisted. A false value never
            // clears an existing true Medical History value.
            $syncResult = $this->medicalHistorySyncService->syncConfirmedVisitConditions(
                $patient,
                (bool) $visit->diabetes,
                (bool) $visit->anemia,
                $visit
            );

            if ($syncResult['changed'] && !empty($syncResult['updated_fields'])) {
                $this->logAction(
                    'MEDICAL_HISTORY_SYNC',
                    'MEDICAL_HISTORY',
                    'Medical History ' . implode(', ', $syncResult['updated_fields']) . ' updated from prenatal visit ID: ' . $visit->id
                );
            }

            // â•â•â• Phase 3 - staff-confirmed follow-up appointment.
            // A null date OR a null time means "no appointment": the
            // risk engine's suggested date is date-only, so it can never
            // reach this call with a time and never books a slot.
            $followUpResult = $this->followUps->sync(
                $visit,
                $finalNextVisit,
                $request->input('next_visit_time'),
                $request->user()
            );

                return $visit;
            });
        } catch (DomainException $exception) {
            return back()->withErrors([
                'next_visit_time' => $exception->getMessage(),
            ])->withInput();
        }

        // Phase 3: appointment notifications fire ONLY after the transaction
        // above has committed (same post-commit rule the appointment
        // controllers follow) and only when something really changed.
        $this->notifyFollowUpOutcome($followUpResult);

        // Persisted clinical state transitions -> in-app notifications.
        // A freshly stored visit is one event: each new urgent / pending-repeat
        // state is notified exactly once (never on later page renders).
        // Persisted clinical state transitions -> in-app notifications.
        // A freshly stored visit is one event: each new urgent / pending-repeat
        // state is notified exactly once (never on later page renders).
        if ($visit->urgency === 'URGENT_CLINICAL_REVIEW') {
            $this->notifications->notifyUrgentBloodPressure($visit);
        }

        if ($visit->bp_verification_status === 'PENDING_REPEAT') {
            $this->notifications->notifyPendingRepeatBloodPressure($visit);
        }

        // Non-HIGH -> HIGH transition of this patient's EFFECTIVE risk (no
        // prior assessment, or a previous non-HIGH assessment). HIGH -> HIGH
        // is a no-op inside the service, so re-saving cannot re-alert.
        $this->notifications->notifyHighRiskTransition((int) $patient->id, $previousEffectiveRisk);

        // Log repeat BP recording only after the visit persisted successfully
        if ($repeatBpInputs) {
            $this->logAction(
                'BP_REPEAT_RECORDED',
                'PRENATAL_VISIT',
                'Repeat BP recorded for patient: ' . $patient->first_name . ' ' . $patient->last_name
            );
        }

        if (!empty($patient->email) && $request->next_visit_date) {
    try {
        Log::info('PRENATAL CREATE EMAIL ATTEMPT: ' . $patient->email);

        Mail::to($patient->email)
            ->send(new PrenatalVisitReminderMail($patient, $visit));

        Log::info('PRENATAL CREATE EMAIL SENT SUCCESSFULLY: ' . $patient->email);

    } catch (\Exception $e) {
        Log::error('PRENATAL CREATE EMAIL FAILED: ' . $e->getMessage() . ' | Patient ID: ' . $patient->id . ' | Visit ID: ' . $visit->id);
    }
} else {
    Log::info('PRENATAL CREATE EMAIL SKIPPED: Patient ID ' . $patient->id . ' â€” ' . (empty($patient->email) ? 'no email' : 'no next_visit_date'));
}

        // âœ… AUDIT LOG
        $this->logAction(
            'CREATE',
            'PRENATAL_VISIT',
            'Added visit for patient: ' . $patient->first_name . ' ' . $patient->last_name
        );

        if ($lockedPatientId) {
            session()->forget('prenatal_visit_locked_patient_id');
        }

        return redirect()->route('prenatal-visits.index')
            ->with('success', 'Prenatal visit added successfully with risk assessment');
    }

    /**
     * Read-only print view for a single, already-persisted prenatal visit.
     *
     * Uses only the visit's stored assessment data â€” no risk assessment is
     * re-run and no risk level is recalculated. The visit is identified by
     * its own ID, so this always renders that exact visit's own patient and
     * assessment, never another visit's or another patient's data.
     */
    public function print($visit)
    {
        $prenatalVisit = PrenatalVisit::with('patient')->findOrFail($visit);

        return view('prenatal_visits.print', ['visit' => $prenatalVisit]);
    }

    public function edit($id)
    {
        $visit = PrenatalVisit::findOrFail($id);
        $patients = Patient::all();
        $referenceDate = request()->old('visit_date', $visit->visit_date?->toDateString());
        $expectedGestationalAge = $this->calculateInitialGestationalAge($visit->patient, $referenceDate);
        $gestationalAgeHint = $this->gestationalAgeHint($visit->patient, $referenceDate, $expectedGestationalAge);
        $hasOldGestationalAge = request()->session()->hasOldInput('gestational_age');

        $sourcePreview = $this->sourcePreview(
            $this->contextBuilder->buildForPatient(
                $visit->patient,
                $visit,
                [],
                $visit->visit_date?->toDateString()
            )
        );

        // Phase 3: the LIVE follow-up appointment linked to this visit (if
        // any). Drives the slot picker's ignore_appointment_id (so the
        // visit's own slot stays selectable) and the explicit Clear
        // Scheduled Next Visit confirmation block on the form.
        $followUp = $visit->activeFollowUpAppointment();

        return view('prenatal_visits.edit', compact(
            'visit',
            'patients',
            'sourcePreview',
            'expectedGestationalAge',
            'gestationalAgeHint',
            'hasOldGestationalAge',
            'followUp'
        ));
    }

    private function calculateInitialGestationalAge(?Patient $patient, ?string $referenceDate): ?float
    {
        if (!$referenceDate || !Carbon::hasFormat($referenceDate, 'Y-m-d')) {
            return null;
        }

        $expected = $this->gestationalAgeCalculator->calculate(
            $patient?->lmp?->toDateString(),
            $referenceDate
        );

        return $expected !== null && $expected >= 4 && $expected <= 42 ? $expected : null;
    }

    private function gestationalAgeHint(?Patient $patient, ?string $referenceDate, ?float $expected): string
    {
        if (!$patient) {
            return 'Select a patient to calculate expected gestational age.';
        }

        if (!$patient->lmp) {
            return 'No LMP available for automatic GA calculation.';
        }

        if (!$referenceDate || !Carbon::hasFormat($referenceDate, 'Y-m-d')) {
            return 'Enter a valid visit date to calculate expected gestational age.';
        }

        if ($referenceDate < $patient->lmp->toDateString()) {
            return 'Visit date cannot be before the patientâ€™s LMP.';
        }

        if ($expected === null) {
            $calculated = $this->gestationalAgeCalculator->calculate(
                $patient->lmp->toDateString(),
                $referenceDate
            );

            return $calculated === null
                ? 'Unable to calculate gestational age from these dates.'
                : sprintf('Calculated GA: %.1f weeks is outside the allowed 4â€“42 week range.', $calculated);
        }

        return sprintf('Expected GA: %.1f weeks based on LMP', $expected);
    }

    /**
     * One-sentence, human-readable summary of which source records will inform
     * the assessment. Read-only; never used for clinical decisions.
     */
    private function sourcePreview(AssessmentContext $context): string
    {
        $ultrasound = $context->ultrasound_date
            ? 'the last ultrasound (dated ' . \Carbon\Carbon::parse($context->ultrasound_date)->format('M d, Y') . ')'
            : 'no ultrasound record';

        $parts = [];
        if ($context->medical_history_exists) {
            $parts[] = 'an active Medical History';
        }
        if ($context->birth_plan_exists) {
            $parts[] = 'an active Birth Plan';
        }

        if (empty($parts)) {
            return "This assessment will use {$ultrasound}, with no active Medical History or Birth Plan on record.";
        }

        return "This assessment will use {$ultrasound} together with " . implode(' and ', $parts) . '.';
    }

    public function update(Request $request, $id)
    {
        $visit = PrenatalVisit::findOrFail($id);
        $originalNextVisitDate = $visit->getOriginal('next_visit_date');
        $originalNextVisitTime = $visit->getOriginal('next_visit_time');

        // â•â•â• Phase 3 - explicit "Clear Scheduled Next Visit" confirmation.
        // When the staff ticks the box the submitted date/time inputs are
        // IGNORED (the browser may still carry stale values, and an overdue
        // date would otherwise fail after_or_equal:today), and the schedule
        // is synchronised to NULL together with the linked appointment.
        $clearRequested = $request->boolean('clear_next_visit');

        // ======================
        // ENHANCED VALIDATION (Same as store)
        // ======================
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'visit_date' => 'required|date|before_or_equal:today',
            'bp_sys' => 'required|numeric|min:60|max:480',
            'bp_dia' => 'required|numeric|min:40|max:350',
            'weight' => 'required|numeric|min:30|max:250|regex:/^\d+(\.\d)?$/',
            'temperature' => 'nullable|numeric|min:35|max:40',

            // Repeat BP (optional, both-or-neither)
            'repeat_bp_sys' => 'nullable|required_with:repeat_bp_dia|numeric|min:60|max:480',
            'repeat_bp_dia' => 'nullable|required_with:repeat_bp_sys|numeric|min:40|max:350',
            'bp_verification_status' => 'nullable|string|in:UNABLE_TO_REPEAT',
            'bp_verification_note' => 'nullable|string|max:500',

            'gestational_age' => 'required|numeric|min:4|max:42',
            'fundic_height' => 'nullable|string|max:50',
            'fetal_heart_tone' => 'nullable|string|max:50',
            'fetal_movement' => 'nullable|string|max:50',
            'presenting_part' => 'nullable|string|max:100',
            'uterine_activity' => 'nullable|string|max:100',
            'cervical_dilation' => 'nullable|numeric|min:0|max:10',
            'bag_of_water' => 'nullable|string|max:50',
            'hypertension' => 'required|boolean',
            'diabetes' => 'required|boolean',
            'anemia' => 'required|boolean',
            'treatment_plan' => 'nullable|string',
            'next_visit_date' => $clearRequested ? 'nullable' : 'nullable|date|after_or_equal:today',
            'next_visit_time' => $clearRequested ? 'nullable' : 'nullable|date_format:H:i',
            'notes' => 'nullable|string'
        ], [
            'bp_sys.min' => 'Systolic BP must be at least 60 mmHg',
            'bp_sys.max' => 'Systolic BP cannot exceed 480 mmHg',
            'bp_dia.min' => 'Diastolic BP must be at least 40 mmHg',
            'bp_dia.max' => 'Diastolic BP cannot exceed 350 mmHg',
            'weight.min' => 'Weight must be at least 30 kg',
            'weight.max' => 'Weight cannot exceed 250 kg',
            'weight.regex' => 'Weight must have at most 1 decimal place',
            'gestational_age.min' => 'Gestational age must be at least 4 weeks',
            'gestational_age.max' => 'Gestational age cannot exceed 42 weeks',
            'visit_date.before_or_equal' => 'Visit date cannot be in the future',
            'next_visit_date.after_or_equal' => 'Next visit date must be today or in the future',
            'next_visit_time.date_format' => 'Next visit time must be a valid clock time (HH:MM).',
            'repeat_bp_sys.required_with' => 'Repeat BP systolic is required when repeat diastolic is provided',
            'repeat_bp_dia.required_with' => 'Repeat BP diastolic is required when repeat systolic is provided',
        ]);

        // â•â•â• Phase 3 - resolve the FINAL Next Visit schedule â•â•â•â•â•â•â•â•â•â•â•
        //
        // $clearRequested   -> both halves explicitly NULL (no risk
        //                      suggestion fallback: the staff asked for a
        //                      clean slate and confirmed it).
        // otherwise         -> whatever the form submitted; NULL time is a
        //                      normal date-only schedule (the risk
        //                      suggestion still applies when the DATE is
        //                      empty, exactly as before this phase).
        $activeFollowUp = $clearRequested ? null : $visit->activeFollowUpAppointment();

        $effectiveNextVisitDate = $clearRequested ? null : $request->input('next_visit_date');
        $effectiveNextVisitTime = $clearRequested ? null : $request->input('next_visit_time');

        // A time without a date is an unschedulable half-schedule.
        if (! $clearRequested && $request->filled('next_visit_time') && ! $request->filled('next_visit_date')) {
            return back()->withErrors([
                'next_visit_time' => 'Select a Next Visit Date before choosing a Next Visit Time.',
            ])->withInput();
        }

        // User-approved decision 2: dropping the TIME (or the whole
        // schedule) while a live follow-up appointment exists must be an
        // explicit, confirmed action - never a side effect of an empty
        // field. The edit form renders the matching checkbox + confirm
        // dialog; a forged POST without the flag is refused here.
        if ($activeFollowUp !== null
            && ! $clearRequested
            && ($effectiveNextVisitDate === null || $effectiveNextVisitTime === null)) {
            return back()->withErrors([
                'next_visit_date' => 'A follow-up appointment is booked for this visit. Choose a new date AND time, or tick "Clear Scheduled Next Visit" to cancel the appointment.',
            ])->withInput();
        }

        // Read-only slot pre-check (the visit's OWN appointment is ignored,
        // so re-saving the unchanged schedule always passes).
        try {
            $this->followUps->assertScheduleUsable(
                $effectiveNextVisitDate,
                $effectiveNextVisitTime,
                $activeFollowUp?->id
            );
        } catch (DomainException $exception) {
            return back()->withErrors([
                'next_visit_time' => $exception->getMessage(),
            ])->withInput();
        }

        // ======================
        // LOGICAL VALIDATIONS
        // ======================

        // BP Logic
        if ($request->bp_sys <= $request->bp_dia) {
            return back()->withErrors([
                'bp_sys' => 'Systolic BP must be greater than Diastolic BP',
                'bp_dia' => 'Systolic BP must be greater than Diastolic BP'
            ])->withInput();
        }

        // Repeat BP Logic: Systolic should be greater than Diastolic
        if ($request->filled('repeat_bp_sys') && $request->filled('repeat_bp_dia') && $request->repeat_bp_sys <= $request->repeat_bp_dia) {
            return back()->withErrors([
                'repeat_bp_sys' => 'Repeat systolic BP must be greater than repeat diastolic BP',
                'repeat_bp_dia' => 'Repeat systolic BP must be greater than repeat diastolic BP'
            ])->withInput();
        }

        // UNABLE_TO_REPEAT requires a non-empty verification note
        if ($request->bp_verification_status === 'UNABLE_TO_REPEAT' && trim((string) $request->bp_verification_note) === '') {
            return back()->withErrors([
                'bp_verification_note' => 'A verification note is required when the status is "Unable to Repeat".',
            ])->withInput();
        }

        // Prevent patient reassignment after a visit is recorded
        if ((int) $request->patient_id !== (int) $visit->patient_id) {
            return back()->withErrors([
                'patient_id' => 'The patient cannot be changed after a prenatal visit is recorded.',
            ])->withInput();
        }

        // Gestational age vs LMP validation
        $patient = $visit->patient;
        if ($patient && $patient->lmp) {
            $expectedWeeks = $this->gestationalAgeCalculator->calculate(
                $patient->lmp->toDateString(),
                $request->visit_date
            );

            if ($expectedWeeks === null) {
                return back()->withErrors([
                    'gestational_age' => 'Visit date cannot be before the patient\'s LMP.',
                ])->withInput();
            }

            if (abs($expectedWeeks - (float) $request->gestational_age) > 3) {
                return back()->withErrors([
                    'gestational_age' => "Gestational age doesn't match LMP date. Based on LMP ({$patient->lmp}), expected GA is {$expectedWeeks} weeks (Â±3 weeks allowed)."
                ])->withInput();
            }
        }

        // Detect initial BP change and clear repeat pair if changed
        $initialBpChanged = (
            (int) $request->bp_sys !== (int) $visit->getOriginal('bp_sys') ||
            (int) $request->bp_dia !== (int) $visit->getOriginal('bp_dia')
        );

        $initialBpEdited = $initialBpChanged;
        $repeatBpRecorded = false;

        // ======================
        // CALCULATE FINAL REPEAT-BP STATE FIRST
        // ======================
        // When the initial BP changes, the old repeat pair is stale. Repeat
        // fields submitted during that same request (including prefilled old
        // values) must be ignored so the stale pair is never re-saved.
        //
        // When the initial BP is unchanged and no repeat fields are submitted,
        // the stored repeat pair (if any) must still be fed into the risk
        // assessment so a previously recorded severe repeat is preserved.
        $repeatBpInputs = null;
        if (!$initialBpChanged && $request->filled('repeat_bp_sys') && $request->filled('repeat_bp_dia')) {
            $repeatBpInputs = [
                'bp_sys' => (int) $request->repeat_bp_sys,
                'bp_dia' => (int) $request->repeat_bp_dia,
            ];
        } elseif (!$initialBpChanged && $visit->getOriginal('repeat_bp_sys') !== null) {
            $repeatBpInputs = [
                'bp_sys' => (int) $visit->getOriginal('repeat_bp_sys'),
                'bp_dia' => (int) $visit->getOriginal('repeat_bp_dia'),
            ];
        }

        if ($initialBpChanged) {
            $repeatFields = [
                'repeat_bp_sys' => null,
                'repeat_bp_dia' => null,
                'repeat_bp_recorded_at' => null,
                'repeat_bp_recorded_by' => null,
            ];
        } elseif ($repeatBpInputs) {
            $storedRepeatSys = $visit->getOriginal('repeat_bp_sys');
            $storedRepeatDia = $visit->getOriginal('repeat_bp_dia');

            // Only treat the pair as newly recorded when it is first recorded
            // or its values actually change. An unchanged pair must keep its
            // original recorded_at/recorded_by metadata.
            $repeatBpRecorded = (
                $storedRepeatSys === null ||
                (int) $storedRepeatSys !== $repeatBpInputs['bp_sys'] ||
                (int) $storedRepeatDia !== $repeatBpInputs['bp_dia']
            );

            $repeatFields = $repeatBpRecorded ? [
                'repeat_bp_sys' => $repeatBpInputs['bp_sys'],
                'repeat_bp_dia' => $repeatBpInputs['bp_dia'],
                'repeat_bp_recorded_at' => now(),
                'repeat_bp_recorded_by' => auth()->id(),
            ] : [
                'repeat_bp_sys' => $storedRepeatSys,
                'repeat_bp_dia' => $storedRepeatDia,
                'repeat_bp_recorded_at' => $visit->getOriginal('repeat_bp_recorded_at'),
                'repeat_bp_recorded_by' => $visit->getOriginal('repeat_bp_recorded_by'),
            ];
        } else {
            $repeatFields = [
                'repeat_bp_sys' => $visit->getOriginal('repeat_bp_sys'),
                'repeat_bp_dia' => $visit->getOriginal('repeat_bp_dia'),
                'repeat_bp_recorded_at' => $visit->getOriginal('repeat_bp_recorded_at'),
                'repeat_bp_recorded_by' => $visit->getOriginal('repeat_bp_recorded_by'),
            ];
        }

        // ======================
        // ASSESS RISK
        // ======================
        $bpVerificationStatusInput = $initialBpChanged ? null : $request->bp_verification_status;
        $bpVerificationNote = $initialBpChanged ? null : $request->bp_verification_note;

        $riskAssessment = $this->riskAssessmentService->assess(
            $patient,
            $request->only([
                'bp_sys', 'bp_dia', 'weight', 'gestational_age', 'hypertension', 'diabetes', 'anemia'
            ]),
            $repeatBpInputs,
            $bpVerificationStatusInput,
            $bpVerificationNote,
            $visit->visit_date?->toDateString(),
            $visit
        );

        $risk = $riskAssessment['risk_level'];
        $assessment = $riskAssessment['assessment'];
        $recommendation = $riskAssessment['recommendation'];
        $reasons = $riskAssessment['reasons'];
        $nextVisit = $riskAssessment['nextVisit'];

        // A cleared schedule must stay NULL: it is an explicit staff
        // action, NOT the "leave empty for the risk suggestion" case.
        $finalNextVisit = $clearRequested
            ? null
            : ($effectiveNextVisitDate ?: $nextVisit->toDateString());

        $bpVerificationStatus = $riskAssessment['bp_assessment']['verification_status'] ?? BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED;

        // Capture the pre-update clinical state so notifications fire only on
        // a real transition (e.g. NORMAL -> URGENT), never on every re-save
        // while a state is already active.
        $originalUrgency = $visit->getOriginal('urgency');
        $originalBpVerificationStatus = $visit->getOriginal('bp_verification_status');
        $previousEffectiveRisk = $this->notifications->effectiveRiskLevel((int) $visit->patient_id);

        // ======================
        // APPLY SINGLE COHERENT PERSISTENCE UPDATE
        // ======================
        //
        // Phase 3: the linked follow-up appointment is reconciled INSIDE
        // this same transaction (visit row and appointment row move
        // together), so a slot that vanished mid-edit rolls back the whole
        // save instead of leaving the fields and the calendar disagreeing.
        $nextVisitDateChanged = false;
        $nextVisitTimeChanged = false;
        $followUpResult = ['appointment' => null, 'event' => 'none'];

        try {
            DB::transaction(function () use (
                $visit,
                $request,
                $patient,
                $repeatFields,
                $bpVerificationStatus,
                $riskAssessment,
                $risk,
                $assessment,
                $recommendation,
                $reasons,
                $finalNextVisit,
                $effectiveNextVisitTime,
                $originalNextVisitDate,
                $originalNextVisitTime,
                &$nextVisitDateChanged,
                &$nextVisitTimeChanged,
                &$followUpResult
            ) {
            $visit->update([
                'visit_date' => $request->visit_date,
                'bp_sys' => $request->bp_sys,
                'bp_dia' => $request->bp_dia,
                'weight' => $request->weight,
                'gestational_age' => $request->gestational_age,
                'hypertension' => $request->hypertension,
                'diabetes' => $request->diabetes,
                'anemia' => $request->anemia,
                'notes' => $request->notes,
                'treatment_plan' => $request->treatment_plan,
                'temperature' => $request->temperature,
                'fundic_height' => $request->fundic_height,
                'fetal_heart_tone' => $request->fetal_heart_tone,
                'fetal_movement' => $request->fetal_movement,
                'presenting_part' => $request->presenting_part,
                'uterine_activity' => $request->uterine_activity,
                'cervical_dilation' => $request->cervical_dilation,
                'bag_of_water' => $request->bag_of_water,
                'repeat_bp_sys' => $repeatFields['repeat_bp_sys'],
                'repeat_bp_dia' => $repeatFields['repeat_bp_dia'],
                'repeat_bp_recorded_at' => $repeatFields['repeat_bp_recorded_at'],
                'repeat_bp_recorded_by' => $repeatFields['repeat_bp_recorded_by'],
                'bp_verification_status' => $bpVerificationStatus,
                'risk_level' => $risk,
                'risk_reasons' => $reasons,
                'decision_source' => $riskAssessment['decision_source'] ?? null,
                'missing_records' => $riskAssessment['missing_records'] ?? [],
                'rule_reasons' => $riskAssessment['rule_reasons'] ?? [],
                'ml_prediction' => $riskAssessment['ml_prediction'] ?? null,
                'ml_valid' => $riskAssessment['ml_valid'] ?? false,
                'assessment' => $assessment,
                'recommendation' => $recommendation,
                'next_visit_date' => $finalNextVisit,
                'next_visit_time' => $effectiveNextVisitTime,
                'urgency' => $riskAssessment['urgency'] ?? null,
                'bp_assessment' => $riskAssessment['bp_assessment'] ?? null,
                'factor_evidence' => $riskAssessment['factor_evidence'] ?? [],
                'assessment_metadata' => $this->metadataSerializer->fromResult($riskAssessment, $visit),
            ]);

            // NEXT VISIT SCHEDULE CHANGE DETECTION (inside transaction, no email).
            // Date OR time moving both invalidate a pending reminder: the
            // reminder message describes the schedule the patient was told.
            $nextVisitDateChanged = $visit->next_visit_date != $originalNextVisitDate;
            $nextVisitTimeChanged = $visit->next_visit_time !== $originalNextVisitTime;

            if ($nextVisitDateChanged || $nextVisitTimeChanged) {
                $visit->update([
                    'reminder_tomorrow_sent_at' => null,
                    'reminder_today_sent_at' => null,
                ]);
            }

            // One-way sync of confirmed visit diabetes/anemia into Medical History.
            // Runs only after the visit update has persisted. A false value never
            // clears an existing true Medical History value.
            $syncResult = $this->medicalHistorySyncService->syncConfirmedVisitConditions(
                $patient,
                (bool) $visit->diabetes,
                (bool) $visit->anemia,
                $visit
            );

            if ($syncResult['changed'] && !empty($syncResult['updated_fields'])) {
                $this->logAction(
                    'MEDICAL_HISTORY_SYNC',
                    'MEDICAL_HISTORY',
                    'Medical History ' . implode(', ', $syncResult['updated_fields']) . ' updated from prenatal visit ID: ' . $visit->id
                );
            }

            // â•â•â• Phase 3 - reconcile the staff follow-up appointment â•â•â•
            $followUpResult = $this->followUps->sync(
                $visit,
                $finalNextVisit,
                $effectiveNextVisitTime,
                $request->user()
            );
        });
        } catch (DomainException $exception) {
            return back()->withErrors([
                'next_visit_time' => $exception->getMessage(),
            ])->withInput();
        }

        // Phase 3: appointment notifications fire ONLY after the commit.
        $this->notifyFollowUpOutcome($followUpResult);

        // Persisted clinical state transitions -> in-app notifications.
        // Fires only when a state actually transitioned into the active value.
        if ($visit->urgency === 'URGENT_CLINICAL_REVIEW' && $originalUrgency !== 'URGENT_CLINICAL_REVIEW') {
            $this->notifications->notifyUrgentBloodPressure($visit);
        }

        if ($visit->bp_verification_status === 'PENDING_REPEAT' && $originalBpVerificationStatus !== 'PENDING_REPEAT') {
            $this->notifications->notifyPendingRepeatBloodPressure($visit);
        }

        // Non-HIGH -> HIGH transition of this patient's EFFECTIVE risk. When
        // the latest assessment was already HIGH this is a no-op, so editing
        // an already-HIGH visit never re-alerts the clinic.
        $this->notifications->notifyHighRiskTransition((int) $visit->patient_id, $previousEffectiveRisk);

        // ======================
        // AUDIT LOGS (only after successful persistence)
        // ======================
        if ($initialBpEdited) {
            $this->logAction(
                'BP_INITIAL_EDITED',
                'PRENATAL_VISIT',
                'Initial BP edited for patient ID: ' . $visit->patient_id . ' â€” repeat pair cleared'
            );
        }

        if ($repeatBpRecorded) {
            $this->logAction(
                'BP_REPEAT_RECORDED',
                'PRENATAL_VISIT',
                'Repeat BP recorded for patient ID: ' . $visit->patient_id
            );
        }

        $this->logAction(
            'UPDATE',
            'PRENATAL_VISIT',
            'Updated visit for patient ID: ' . $visit->patient_id
        );

        // ======================
        // NEXT VISIT DATE EMAIL (after commit)
        // ======================
        if ($nextVisitDateChanged || $nextVisitTimeChanged) {
            $patient = $visit->patient;

            if ($patient && !empty($patient->email)) {
                try {
                    Log::info('PRENATAL UPDATE EMAIL ATTEMPT: ' . $patient->email);

                    Mail::to($patient->email)
                        ->send(new PrenatalVisitScheduleUpdatedMail($patient, $visit));

                    Log::info('PRENATAL UPDATE EMAIL SENT SUCCESSFULLY: ' . $patient->email);

                } catch (\Exception $e) {
                    Log::error('PRENATAL UPDATE EMAIL FAILED: ' . $e->getMessage() . ' | Patient ID: ' . $patient->id . ' | Visit ID: ' . $visit->id);
                }
            } else {
                Log::info('PRENATAL UPDATE EMAIL SKIPPED (schedule changed, no patient email): Visit ID ' . $visit->id);
            }
        }

        return redirect()->route('prenatal-visits.index')
            ->with('success', 'Prenatal visit updated with new risk assessment');
    }

    /**
     * Phase 3 - post-commit patient notification for a follow-up change.
     *
     * Reuses the EXISTING appointment status notification (database channel
     * only, event_key de-duplicated) instead of adding a second channel, so
     * the patient sees the same "Appointment Confirmed / Cancelled" message
     * they already get from the calendar - one message per transition, no
     * new email and no new reminder schedule. 'unchanged' and 'none' send
     * nothing at all, so re-saving an untouched visit is silent.
     */
    private function notifyFollowUpOutcome(array $result): void
    {
        $appointment = $result['appointment'] ?? null;

        if (! $appointment instanceof \App\Models\Appointment) {
            return;
        }

        if ($result['event'] === 'created' || $result['event'] === 'rescheduled') {
            $this->notifications->notifyAppointmentStatusChanged($appointment, \App\Models\Appointment::STATUS_CONFIRMED);

            return;
        }

        if ($result['event'] === 'cleared') {
            $this->notifications->notifyAppointmentStatusChanged($appointment, \App\Models\Appointment::STATUS_CANCELLED);
        }
    }

    /**
     * Auto-recalculate risk assessment for all incomplete prenatal visits of a patient.
     * Called when Medical History, Ultrasound, or Birth Plan is created/updated.
     * Only recalculates if all required records now exist.
     *
     * @param int $patientId
     * @return void
     */
    public function recalculateIncompleteVisits($patientId)
    {
        $this->recalculationService->recalculateIncompleteVisits($patientId);
    }

    public function destroy($id)
    {
        $visit = PrenatalVisit::findOrFail($id);
        $patientId = $visit->patient_id;
        $visit->delete();

        // âœ… AUDIT LOG
        $this->logAction(
            'ARCHIVE',
            'PRENATAL_VISIT',
            'Archived visit for patient ID: ' . $patientId
        );

        return redirect()->route('prenatal-visits.index')
            ->with('success', 'Prenatal visit archived successfully.');
    }
}
