<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrenatalVisit;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\PrenatalVisitReminderMail;
use App\Mail\PrenatalVisitScheduleUpdatedMail;
use App\Services\RiskAssessmentService;

class PrenatalVisitController extends Controller
{
    private RiskAssessmentService $riskAssessmentService;

    public function __construct(RiskAssessmentService $riskAssessmentService)
    {
        $this->riskAssessmentService = $riskAssessmentService;
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
        return view('prenatal_visits.create', compact('patients','selectedPatient'));
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
            'patient_id.exists' => 'Selected patient does not exist'
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

        // ======================
        // CREATE VISIT (without risk fields first)
        // ======================
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
        ]);

        // ======================
        // ASSESS RISK
        // ======================
        $riskAssessment = $this->riskAssessmentService->assess($patient, $request->only([
            'bp_sys', 'bp_dia', 'weight', 'gestational_age', 'hypertension', 'diabetes', 'anemia'
        ]));

        $risk = $riskAssessment['risk_level'];
        $assessment = $riskAssessment['assessment'];
        $recommendation = $riskAssessment['recommendation'];
        $reasons = $riskAssessment['reasons'];
        $nextVisit = $riskAssessment['nextVisit'];

        $finalNextVisit = $request->next_visit_date ?: $nextVisit->toDateString();

        // ======================
        // UPDATE VISIT WITH RISK FIELDS
        // ======================
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
        ]);

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
        return view('prenatal_visits.edit', compact('visit','patients'));
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

        // ======================
        // UPDATE CLINICAL FIELDS FIRST
        // ======================
        $visit->update([
            'patient_id' => $request->patient_id,
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
        ]);

        // ======================
        // ASSESS RISK
        // ======================
        $riskAssessment = $this->riskAssessmentService->assess($patient, $request->only([
            'bp_sys', 'bp_dia', 'weight', 'gestational_age', 'hypertension', 'diabetes', 'anemia'
        ]));

        $risk = $riskAssessment['risk_level'];
        $assessment = $riskAssessment['assessment'];
        $recommendation = $riskAssessment['recommendation'];
        $reasons = $riskAssessment['reasons'];
        $nextVisit = $riskAssessment['nextVisit'];

        $finalNextVisit = $request->next_visit_date ?: $nextVisit->toDateString();

        // ======================
        // UPDATE RISK FIELDS ONLY
        // ======================
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
        ]);
        
        // ======================
        // NEXT VISIT DATE CHANGE DETECTION
        // ======================
        $nextVisitDateChanged = $visit->next_visit_date != $originalNextVisitDate;

        if ($nextVisitDateChanged) {
            $visit->update([
                'reminder_tomorrow_sent_at' => null,
                'reminder_today_sent_at' => null,
            ]);

            $patient = Patient::find($visit->patient_id);

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

        // ✅ AUDIT LOG
        $this->logAction(
            'UPDATE',
            'PRENATAL_VISIT',
            'Updated visit for patient ID: ' . $visit->patient_id
        );

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
        // Check if all required records exist
        $hasMedicalHistory = \App\Models\MedicalHistory::where('patient_id', $patientId)->exists();
        $hasUltrasound = \App\Models\Ultrasound::where('patient_id', $patientId)->exists();
        $hasBirthPlan = \App\Models\BirthPlan::where('patient_id', $patientId)->exists();

        // Only recalculate if all required records are now complete
        if (!$hasMedicalHistory || !$hasUltrasound || !$hasBirthPlan) {
            return;
        }

        $patient = Patient::find($patientId);
        if (!$patient) {
            return;
        }

        // Find all visits for this patient (not just incomplete ones)
        $visits = PrenatalVisit::where('patient_id', $patientId)->get();

        foreach ($visits as $visit) {
            $riskAssessment = $this->riskAssessmentService->assess($patient, [
                'bp_sys' => $visit->bp_sys,
                'bp_dia' => $visit->bp_dia,
                'weight' => $visit->weight,
                'gestational_age' => $visit->gestational_age,
                'hypertension' => $visit->hypertension,
                'diabetes' => $visit->diabetes,
                'anemia' => $visit->anemia,
            ]);

            $visit->update([
                'risk_level' => $riskAssessment['risk_level'],
                'assessment' => $riskAssessment['assessment'],
                'recommendation' => $riskAssessment['recommendation'],
                'risk_reasons' => $riskAssessment['reasons'],
                'decision_source' => $riskAssessment['decision_source'] ?? null,
                'missing_records' => $riskAssessment['missing_records'] ?? [],
                'rule_reasons' => $riskAssessment['rule_reasons'] ?? [],
                'ml_prediction' => $riskAssessment['ml_prediction'] ?? null,
                'ml_valid' => $riskAssessment['ml_valid'] ?? false,
                'next_visit_date' => $visit->next_visit_date ?: $riskAssessment['nextVisit']->toDateString(),
            ]);

            Log::info('Auto-recalculated risk assessment for patient ID: ' . $patientId . ', visit ID: ' . $visit->id);
        }
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