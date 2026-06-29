<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrenatalVisit;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\PrenatalVisitReminderMail;

class PrenatalVisitController extends Controller
{
    /**
     * Assess pregnancy risk using strict evaluation order.
     *
     * Evaluation order:
     * 1. Check medical history requirement (MUST exist)
     * 2. Rule-based risk factors
     * 3. ML output HIGH
     * 4. ML output LOW (only if valid)
     * 5. ASSESSMENT INCOMPLETE (if ML invalid or medical history missing)
     *
     * @param Patient $patient
     * @param Request $request
     * @return array Risk assessment result with risk_level, assessment, recommendation, reasons, nextVisit
     */
    private function assessRisk(Patient $patient, Request $request)
    {
        // ======================
        // STEP 1: CHECK MEDICAL HISTORY REQUIREMENT
        // ======================
        $hasMedicalHistory = \App\Models\MedicalHistory::where('patient_id', $patient->id)->exists();

        if (!$hasMedicalHistory) {
            return [
                'risk_level' => 'ASSESSMENT INCOMPLETE',
                'assessment' => 'Assessment incomplete. Medical history record is required before final risk classification.',
                'recommendation' => 'Complete the medical history record before final risk classification. This is system-generated and is not a medical diagnosis.',
                'reasons' => [],
                'nextVisit' => now()->addDays(30)
            ];
        }

        // ======================
        // STEP 2: EVALUATE RULE-BASED RISK FACTORS
        // ======================
        $reasons = [];

        // Age checks
        if ($patient->age < 19) {
            $reasons[] = "Teenage pregnancy (under 19)";
        } elseif ($patient->age >= 35 && $patient->gravida == 1 && $patient->para == 0) {
            $reasons[] = "Advanced maternal age (35+) and first pregnancy";
        }

        // Blood pressure checks
        if ($request->bp_sys >= 140 || $request->bp_dia >= 90) {
            $reasons[] = "Hypertension (BP: {$request->bp_sys}/{$request->bp_dia})";

            if ($request->bp_sys >= 160 || $request->bp_dia >= 110) {
                $reasons[] = "Severe hypertension (BP: {$request->bp_sys}/{$request->bp_dia})";
            }
        }

        // Medical conditions
        if ($request->diabetes == 1) {
            $reasons[] = "Diabetes";
        }

        if ($request->anemia == 1) {
            $reasons[] = "Anemia";
        }

        if ($patient->previous_cs == 1) {
            $reasons[] = "Previous cesarean section";
        }

        if ($patient->miscarriage >= 3) {
            $reasons[] = "History of " . $patient->miscarriage . " miscarriage(s)";
        }

        // Ultrasound findings
        $ultrasound = \App\Models\Ultrasound::where('patient_id', $patient->id)
            ->latest()
            ->first();

        if ($ultrasound) {
            $presentation = strtoupper(trim((string) $ultrasound->presentation));
            $amnioticFluid = strtoupper(trim((string) $ultrasound->amniotic_fluid));
            $fetalHeartbeat = strtoupper(trim((string) $ultrasound->fetal_heartbeat));

            if (in_array($presentation, ['BREECH', 'TRANSVERSE', 'OBLIQUE'], true)) {
                $reasons[] = "Abnormal fetal presentation ({$presentation})";
            }

            if (in_array($amnioticFluid, ['LOW', 'HIGH'], true)) {
                $reasons[] = "Amniotic fluid abnormality ({$amnioticFluid})";
            }

            if (in_array($fetalHeartbeat, ['WEAK', 'ABNORMAL', 'ABSENT'], true)) {
                $reasons[] = "Fetal heartbeat abnormality ({$fetalHeartbeat})";
            }
        }

        // If any rule-based risk factor matched, return HIGH
        if (!empty($reasons)) {
            $uniqueReasons = array_unique($reasons);
            $assessment = "High-risk pregnancy. Risk factors identified: " . implode(", ", array_slice($uniqueReasons, 0, 3));
            if (count($uniqueReasons) > 3) {
                $assessment .= " and " . (count($uniqueReasons) - 3) . " more factor(s).";
            }

            return [
                'risk_level' => 'HIGH',
                'assessment' => $assessment,
                'recommendation' => 'Referral or clinic staff review is recommended. This is system-generated and is not a medical diagnosis.',
                'reasons' => $uniqueReasons,
                'nextVisit' => now()->addDays(3)
            ];
        }

        // ======================
        // STEP 3: EVALUATE ML OUTPUT
        // ======================
        $inputs = [
            $patient->age,
            $patient->gravida,
            $patient->para,
            $request->bp_sys,
            $request->bp_dia,
            $request->weight,
            $request->gestational_age,
            $request->hypertension,
            $request->diabetes,
            $patient->previous_cs,
            $patient->miscarriage,
            $request->anemia
        ];

        $python = escapeshellarg('C:\\Users\\BJ\\maternity-system\\venv\\Scripts\\python.exe');
        $script = escapeshellarg(base_path('maternal-risk-ml/predict.py'));
        $inputsStr = implode(" ", array_map('escapeshellarg', $inputs));
        $command = "{$python} {$script} {$inputsStr} 2>&1";

        $output = shell_exec($command);
        $rawMlOutput = trim((string) $output);

        // Log raw ML output for debugging
        Log::info('ML RISK OUTPUT: ' . $rawMlOutput . ' | Patient ID: ' . $patient->id);

        $mlRisk = null;
        $mlRiskValid = false;

        if ($rawMlOutput !== '' && !preg_match('/error|exception|traceback|failed|unable/i', $rawMlOutput)) {
            $normalizedMlRisk = strtoupper($rawMlOutput);
            if (in_array($normalizedMlRisk, ['LOW', 'HIGH'], true)) {
                $mlRisk = $normalizedMlRisk;
                $mlRiskValid = true;
            }
        }

        // Step 3A: ML output is HIGH
        if ($mlRisk === 'HIGH') {
            return [
                'risk_level' => 'HIGH',
                'assessment' => 'High-risk pregnancy. The ML assessment indicated high risk.',
                'recommendation' => 'Referral or clinic staff review is recommended. This is system-generated and is not a medical diagnosis.',
                'reasons' => [],
                'nextVisit' => now()->addDays(3)
            ];
        }

        // Step 3B: ML output is valid and LOW
        if ($mlRiskValid && $mlRisk === 'LOW') {
            return [
                'risk_level' => 'LOW',
                'assessment' => 'Low-risk pregnancy. No rule-based risk factors identified.',
                'recommendation' => 'Continue routine prenatal checkups as advised by clinic personnel. This is system-generated and is not a medical diagnosis.',
                'reasons' => [],
                'nextVisit' => now()->addDays(30)
            ];
        }

        // Step 3C: ML output is invalid or unavailable
        return [
            'risk_level' => 'ASSESSMENT INCOMPLETE',
            'assessment' => 'Assessment incomplete. Missing or invalid information prevented final risk classification.',
            'recommendation' => 'Complete the missing record(s) before final risk classification. This is system-generated and is not a medical diagnosis.',
            'reasons' => [],
            'nextVisit' => now()->addDays(30)
        ];
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

        
        $riskAssessment = $this->assessRisk($patient, $request);

        $risk = $riskAssessment['risk_level'];
        $assessment = $riskAssessment['assessment'];
        $recommendation = $riskAssessment['recommendation'];
        $reasons = $riskAssessment['reasons'];
        $nextVisit = $riskAssessment['nextVisit'];

        $finalNextVisit = $request->next_visit_date ?: $nextVisit->toDateString();


        // ======================
        // CREATE VISIT
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
            'risk_level' => $risk,
            'risk_reasons' => json_encode($reasons),
            'assessment' => $assessment,
            'recommendation' => $recommendation,
            'next_visit_date' => $finalNextVisit,
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

        if (!empty($patient->email)) {
    try {
        Log::info('EMAIL ATTEMPT STARTED FOR: ' . $patient->email);

        Mail::to($patient->email)
            ->send(new PrenatalVisitReminderMail($patient, $visit));

        Log::info('EMAIL SENT OR LOGGED SUCCESSFULLY FOR: ' . $patient->email);

    } catch (\Exception $e) {
        Log::error('PRENATAL EMAIL ERROR: ' . $e->getMessage());
    }
} else {
    Log::warning('EMAIL SKIPPED: Patient has no email. Patient ID: ' . $patient->id);

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
        // ML INPUT (Same as store)
        // ======================
        // Note: Risk assessment is now delegated to the helper method assessRisk()
        // which is called below to maintain consistency between store() and update().


        $riskAssessment = $this->assessRisk($patient, $request);

        $risk = $riskAssessment['risk_level'];
        $assessment = $riskAssessment['assessment'];
        $recommendation = $riskAssessment['recommendation'];
        $reasons = $riskAssessment['reasons'];
        $nextVisit = $riskAssessment['nextVisit'];

        $finalNextVisit = $request->next_visit_date ?: $nextVisit->toDateString();


        // ======================
        // UPDATE DATA
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
            'risk_level' => $risk,
            'risk_reasons' => json_encode($reasons),
            'assessment' => $assessment,
            'recommendation' => $recommendation,
            'next_visit_date' => $finalNextVisit,
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
        
        // ✅ AUDIT LOG
        $this->logAction(
            'UPDATE',
            'PRENATAL_VISIT',
            'Updated visit for patient ID: ' . $visit->patient_id
        );

        return redirect()->route('prenatal-visits.index')
            ->with('success', 'Prenatal visit updated with new risk assessment');
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