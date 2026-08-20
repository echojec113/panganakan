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
use App\Services\RiskAssessmentService;
use App\Services\SystemNotificationService;
use App\ValueObjects\AssessmentContext;

class PrenatalVisitController extends Controller
{
    private RiskAssessmentService $riskAssessmentService;
    private PatientAssessmentRecalculationService $recalculationService;
    private MedicalHistoryConditionSyncService $medicalHistorySyncService;
    private AssessmentMetadataSerializer $metadataSerializer;
    private AssessmentContextBuilder $contextBuilder;
    private SystemNotificationService $notifications;

    public function __construct(
        RiskAssessmentService $riskAssessmentService,
        PatientAssessmentRecalculationService $recalculationService,
        MedicalHistoryConditionSyncService $medicalHistorySyncService,
        AssessmentMetadataSerializer $metadataSerializer,
        AssessmentContextBuilder $contextBuilder,
        SystemNotificationService $notifications
    ) {
        $this->riskAssessmentService = $riskAssessmentService;
        $this->recalculationService = $recalculationService;
        $this->medicalHistorySyncService = $medicalHistorySyncService;
        $this->metadataSerializer = $metadataSerializer;
        $this->contextBuilder = $contextBuilder;
        $this->notifications = $notifications;
    }


    public function index()
    {
        $visits = PrenatalVisit::with('patient')->latest()->get();
        return view('prenatal_visits.index', compact('visits'));
    }

    public function create(Request $request)
    {
        $patients = Patient::all();
        $selectedPatient = $request->patient_id;

        $sourcePreview = null;
        if ($selectedPatient) {
            $patient = Patient::find($selectedPatient);
            if ($patient) {
                $sourcePreview = $this->sourcePreview($this->contextBuilder->buildForPatient($patient, null, []));
            }
        }

        return view('prenatal_visits.create', compact('patients', 'selectedPatient', 'sourcePreview'));
    }

    public function store(Request $request)
    {
        // ======================
        // ENHANCED VALIDATION
        // ======================
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'visit_date' => 'required|date|before_or_equal:today',

            // Vital Signs
            'bp_sys' => 'required|numeric|min:60|max:200',
            'bp_dia' => 'required|numeric|min:40|max:130',
            'weight' => 'required|numeric|min:30|max:150',
            'temperature' => 'nullable|numeric|min:35|max:40',

            // Repeat BP (optional, both-or-neither)
            'repeat_bp_sys' => 'nullable|required_with:repeat_bp_dia|numeric|min:60|max:200',
            'repeat_bp_dia' => 'nullable|required_with:repeat_bp_sys|numeric|min:40|max:130',
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
            'notes' => 'nullable|string'
        ], [
            // Custom error messages
            'bp_sys.min' => 'Systolic BP must be at least 60 mmHg',
            'bp_sys.max' => 'Systolic BP cannot exceed 200 mmHg',
            'bp_dia.min' => 'Diastolic BP must be at least 40 mmHg',
            'bp_dia.max' => 'Diastolic BP cannot exceed 130 mmHg',
            'weight.min' => 'Weight must be at least 30 kg',
            'weight.max' => 'Weight cannot exceed 150 kg',
            'temperature.min' => 'Temperature must be at least 35°C',
            'temperature.max' => 'Temperature cannot exceed 40°C',
            'gestational_age.min' => 'Gestational age must be at least 4 weeks',
            'gestational_age.max' => 'Gestational age cannot exceed 42 weeks',
            'cervical_dilation.min' => 'Cervical dilation cannot be negative',
            'cervical_dilation.max' => 'Cervical dilation cannot exceed 10 cm',
            'visit_date.before_or_equal' => 'Visit date cannot be in the future',
            'next_visit_date.after_or_equal' => 'Next visit date must be today or in the future',
            'patient_id.exists' => 'Selected patient does not exist',
            'repeat_bp_sys.required_with' => 'Repeat BP systolic is required when repeat diastolic is provided',
            'repeat_bp_dia.required_with' => 'Repeat BP diastolic is required when repeat systolic is provided',
        ]);

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
            $lmpDate = Carbon::parse($patient->lmp);
            $visitDate = Carbon::parse($request->visit_date);
            $expectedWeeks = $lmpDate->diffInWeeks($visitDate);

            if (abs($expectedWeeks - $request->gestational_age) > 3) {
                return back()->withErrors([
                    'gestational_age' => "Gestational age doesn't match LMP date. Based on LMP ({$patient->lmp}), expected GA is about {$expectedWeeks} weeks (±3 weeks allowed)."
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

        // ======================
        // CREATE VISIT + RISK FIELDS IN ONE TRANSACTION
        // ======================
        $visit = DB::transaction(function () use ($request, $patient, $repeatBpInputs, $riskAssessment, $risk, $assessment, $recommendation, $reasons, $finalNextVisit) {
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
                'next_visit_date' => $request->next_visit_date,
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

            return $visit;
        });

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
    Log::info('PRENATAL CREATE EMAIL SKIPPED: Patient ID ' . $patient->id . ' — ' . (empty($patient->email) ? 'no email' : 'no next_visit_date'));
}

        // ✅ AUDIT LOG
        $this->logAction(
            'CREATE',
            'PRENATAL_VISIT',
            'Added visit for patient: ' . $patient->first_name . ' ' . $patient->last_name
        );

        return redirect()->route('prenatal-visits.index')
            ->with('success', 'Prenatal visit added successfully with risk assessment');
    }

    public function edit($id)
    {
        $visit = PrenatalVisit::findOrFail($id);
        $patients = Patient::all();

        $sourcePreview = $this->sourcePreview(
            $this->contextBuilder->buildForPatient(
                $visit->patient,
                $visit,
                [],
                $visit->visit_date?->toDateString()
            )
        );

        return view('prenatal_visits.edit', compact('visit', 'patients', 'sourcePreview'));
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

        // ======================
        // ENHANCED VALIDATION (Same as store)
        // ======================
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'visit_date' => 'required|date|before_or_equal:today',
            'bp_sys' => 'required|numeric|min:60|max:200',
            'bp_dia' => 'required|numeric|min:40|max:130',
            'weight' => 'required|numeric|min:30|max:150',
            'temperature' => 'nullable|numeric|min:35|max:40',

            // Repeat BP (optional, both-or-neither)
            'repeat_bp_sys' => 'nullable|required_with:repeat_bp_dia|numeric|min:60|max:200',
            'repeat_bp_dia' => 'nullable|required_with:repeat_bp_sys|numeric|min:40|max:130',
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
            'next_visit_date' => 'nullable|date|after_or_equal:today',
            'notes' => 'nullable|string'
        ], [
            'bp_sys.min' => 'Systolic BP must be at least 60 mmHg',
            'bp_sys.max' => 'Systolic BP cannot exceed 200 mmHg',
            'bp_dia.min' => 'Diastolic BP must be at least 40 mmHg',
            'bp_dia.max' => 'Diastolic BP cannot exceed 130 mmHg',
            'weight.min' => 'Weight must be at least 30 kg',
            'weight.max' => 'Weight cannot exceed 150 kg',
            'gestational_age.min' => 'Gestational age must be at least 4 weeks',
            'gestational_age.max' => 'Gestational age cannot exceed 42 weeks',
            'visit_date.before_or_equal' => 'Visit date cannot be in the future',
            'next_visit_date.after_or_equal' => 'Next visit date must be today or in the future',
            'repeat_bp_sys.required_with' => 'Repeat BP systolic is required when repeat diastolic is provided',
            'repeat_bp_dia.required_with' => 'Repeat BP diastolic is required when repeat systolic is provided',
        ]);

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
            $lmpDate = Carbon::parse($patient->lmp);
            $visitDate = Carbon::parse($request->visit_date);
            $expectedWeeks = $lmpDate->diffInWeeks($visitDate);

            if (abs($expectedWeeks - $request->gestational_age) > 3) {
                return back()->withErrors([
                    'gestational_age' => "Gestational age doesn't match LMP date. Based on LMP ({$patient->lmp}), expected GA is about {$expectedWeeks} weeks (±3 weeks allowed)."
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

        $finalNextVisit = $request->next_visit_date ?: $nextVisit->toDateString();

        $bpVerificationStatus = $riskAssessment['bp_assessment']['verification_status'] ?? BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED;

        // Capture the pre-update clinical state so notifications fire only on
        // a real transition (e.g. NORMAL -> URGENT), never on every re-save
        // while a state is already active.
        $originalUrgency = $visit->getOriginal('urgency');
        $originalBpVerificationStatus = $visit->getOriginal('bp_verification_status');

        // ======================
        // APPLY SINGLE COHERENT PERSISTENCE UPDATE
        // ======================
        $nextVisitDateChanged = false;

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
            $originalNextVisitDate,
            &$nextVisitDateChanged
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
                'urgency' => $riskAssessment['urgency'] ?? null,
                'bp_assessment' => $riskAssessment['bp_assessment'] ?? null,
                'factor_evidence' => $riskAssessment['factor_evidence'] ?? [],
                'assessment_metadata' => $this->metadataSerializer->fromResult($riskAssessment, $visit),
            ]);

            // NEXT VISIT DATE CHANGE DETECTION (inside transaction, no email)
            $nextVisitDateChanged = $visit->next_visit_date != $originalNextVisitDate;

            if ($nextVisitDateChanged) {
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
        });

        // Persisted clinical state transitions -> in-app notifications.
        // Fires only when a state actually transitioned into the active value.
        if ($visit->urgency === 'URGENT_CLINICAL_REVIEW' && $originalUrgency !== 'URGENT_CLINICAL_REVIEW') {
            $this->notifications->notifyUrgentBloodPressure($visit);
        }

        if ($visit->bp_verification_status === 'PENDING_REPEAT' && $originalBpVerificationStatus !== 'PENDING_REPEAT') {
            $this->notifications->notifyPendingRepeatBloodPressure($visit);
        }

        // ======================
        // AUDIT LOGS (only after successful persistence)
        // ======================
        if ($initialBpEdited) {
            $this->logAction(
                'BP_INITIAL_EDITED',
                'PRENATAL_VISIT',
                'Initial BP edited for patient ID: ' . $visit->patient_id . ' — repeat pair cleared'
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
        if ($nextVisitDateChanged) {
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

        // ✅ AUDIT LOG
        $this->logAction(
            'DELETE',
            'PRENATAL_VISIT',
            'Deleted visit for patient ID: ' . $patientId
        );

        return redirect()->route('prenatal-visits.index')
            ->with('success', 'Patient record has been deleted.')
            ->with('delete_success', true);
    }
}
