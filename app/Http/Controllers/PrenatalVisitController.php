<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrenatalVisit;
use App\Models\Patient;
use Carbon\Carbon;

class PrenatalVisitController extends Controller
{
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
        // ML RISK ASSESSMENT
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

        $python = '"C:\\Users\\BJ\\maternity-system\\venv\\Scripts\\python.exe"';
        $script = '"' . base_path('maternal-risk-ml/predict.py') . '"';
        $command = $python . " " . $script . " " . implode(" ", $inputs) . " 2>&1";
        $output = shell_exec($command);
        $risk = strtoupper(trim($output));
        
        if ($risk == "" || $risk == null) {
            $risk = "LOW";
        }

        // ================================
        // CONDITION DETECTOR (Enhanced)
        // ================================
        $reasons = [];
        $alerts = [];
        $recommendations = [];

        // 🔴 AGE
        if ($patient->age < 19) {
            $reasons[] = "Teenage pregnancy (under 19)";
            $alerts[] = "Adolescent pregnancy risk";
            $recommendations[] = "Requires specialized adolescent prenatal care";
        } elseif ($patient->age >= 35) {
            $reasons[] = "Advanced maternal age (35+)";
            $alerts[] = "Age-related pregnancy risk";
            $recommendations[] = "Requires closer prenatal monitoring due to maternal age";
        }

        // 🔴 BLOOD PRESSURE (Enhanced thresholds)
        if ($request->bp_sys >= 140 || $request->bp_dia >= 90) {
            $reasons[] = "Hypertension (BP: {$request->bp_sys}/{$request->bp_dia})";
            $alerts[] = "Elevated blood pressure";
            $recommendations[] = "Monitor blood pressure regularly, check for pre-eclampsia symptoms";
            
            if ($request->bp_sys >= 160 || $request->bp_dia >= 110) {
                $reasons[] = "Severe hypertension";
                $alerts[] = "Critical BP elevation";
                $recommendations[] = "URGENT: Immediate medical evaluation required";
            }
        }

        // 🔴 WEIGHT (BMI check - requires height from patient)
        if ($request->weight && $patient->height) {
            $bmi = $request->weight / (($patient->height / 100) ** 2);
            if ($bmi < 18.5) {
                $reasons[] = "Underweight (BMI: " . round($bmi, 1) . ")";
                $recommendations[] = "Nutritional counseling recommended";
            } elseif ($bmi > 30) {
                $reasons[] = "Obese (BMI: " . round($bmi, 1) . ")";
                $recommendations[] = "Weight management and specialized care needed";
            }
        }

        // 🔴 DIABETES
        if ($request->diabetes == 1) {
            $reasons[] = "Diabetes";
            $alerts[] = "Blood sugar risk detected";
            $recommendations[] = "Monitor blood glucose, consult endocrinologist";
        }

        // 🔴 ANEMIA
        if ($request->anemia == 1) {
            $reasons[] = "Anemia";
            $alerts[] = "Low hemoglobin condition";
            $recommendations[] = "Provide iron supplementation and proper nutrition";
        }

        // 🔴 PREVIOUS CS
        if ($patient->previous_cs == 1) {
            $reasons[] = "Previous cesarean section";
            $alerts[] = "History of surgical delivery";
            $recommendations[] = "Careful delivery planning, VBAC assessment needed";
        }

        // 🔴 MULTIPLE MISCARRIAGE
        if ($patient->miscarriage >= 2) {
            $reasons[] = "History of " . $patient->miscarriage . " miscarriage(s)";
            $alerts[] = "Recurrent pregnancy loss";
            $recommendations[] = "Specialist consultation for recurrent loss";
        }

        // 🔴 GESTATIONAL AGE - Preterm risk
        if ($request->gestational_age < 37 && $request->gestational_age >= 20) {
            $reasons[] = "Preterm gestation ({$request->gestational_age} weeks)";
            $alerts[] = "Preterm labor risk";
            $recommendations[] = "Monitor for preterm labor signs, consider corticosteroids";
        }

        // 🔴 ULTRASOUND
        $ultrasound = \App\Models\Ultrasound::where('patient_id', $request->patient_id)
            ->latest()
            ->first();

        if ($ultrasound) {
            if ($ultrasound->presentation === 'Breech') {
                $reasons[] = "Breech presentation";
                $alerts[] = "Abnormal fetal position";
                $recommendations[] = "Refer for delivery planning, consider ECV";
            }

            if ($ultrasound->amniotic_fluid === 'Low') {
                $reasons[] = "Low amniotic fluid";
                $alerts[] = "Oligohydramnios risk";
                $recommendations[] = "Immediate clinical evaluation required";
            }

            if ($ultrasound->amniotic_fluid === 'High') {
                $reasons[] = "High amniotic fluid";
                $alerts[] = "Polyhydramnios risk";
                $recommendations[] = "Evaluate for gestational diabetes, fetal anomalies";
            }

            if ($ultrasound->fetal_heartbeat === 'Weak') {
                $reasons[] = "Weak fetal heartbeat";
                $alerts[] = "Possible fetal distress";
                $recommendations[] = "Urgent monitoring required, consider NST";
            }
        }

        // ================================
        // DOH RULE OVERRIDE
        // ================================
        if (!empty($reasons)) {
            $risk = "HIGH";
        }

        // ================================
        // FINAL DECISION ENGINE
        // ================================
        $today = now();

        if ($risk === "HIGH") {
            $assessment = "High-risk pregnancy. Risk factors identified: " . implode(", ", array_slice($reasons, 0, 3));
            if (count($reasons) > 3) {
                $assessment .= " and " . (count($reasons) - 3) . " more factor(s).";
            }
            
            if (!empty($recommendations)) {
                $recommendation = implode('. ', array_unique($recommendations));
            } else {
                $recommendation = "Close monitoring required with follow-up in 3 days.";
            }
            
            $recommendation .= " This recommendation is generated by the system and is not a medical diagnosis.";
            $nextVisit = $today->copy()->addDays(3);
        } else {
            $assessment = "Low-risk pregnancy. No significant risk factors identified.";
            $recommendation = "Continue routine prenatal care. Maintain healthy lifestyle and regular check-ups. This recommendation is generated by the system and is not a medical diagnosis.";
            $nextVisit = $today->copy()->addDays(30);
        }

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

        $python = '"C:\\Users\\BJ\\maternity-system\\venv\\Scripts\\python.exe"';
        $script = '"' . base_path('maternal-risk-ml/predict.py') . '"';
        $command = $python . " " . $script . " " . implode(" ", $inputs) . " 2>&1";
        $output = shell_exec($command);
        $risk = strtoupper(trim($output));

        if ($risk == "" || $risk == null) {
            $risk = "LOW";
        }

        // ======================
        // RULE ENGINE (Enhanced)
        // ======================
        $reasons = [];
        $recommendations = [];

        if ($patient->age < 19) {
            $reasons[] = "Teenage pregnancy (under 19)";
            $recommendations[] = "Requires specialized adolescent prenatal care";
        } elseif ($patient->age >= 35) {
            $reasons[] = "Advanced maternal age (35+)";
            $recommendations[] = "Requires closer prenatal monitoring due to maternal age";
        }

        if ($request->bp_sys >= 140 || $request->bp_dia >= 90) {
            $reasons[] = "Hypertension (BP: {$request->bp_sys}/{$request->bp_dia})";
            $recommendations[] = "Monitor blood pressure regularly, check for pre-eclampsia symptoms";
        }

        if ($request->diabetes == 1) {
            $reasons[] = "Diabetes";
            $recommendations[] = "Monitor blood glucose, consult endocrinologist";
        }

        if ($request->anemia == 1) {
            $reasons[] = "Anemia";
            $recommendations[] = "Iron supplementation recommended";
        }

        if ($patient->previous_cs == 1) {
            $reasons[] = "Previous cesarean section";
            $recommendations[] = "Careful delivery planning, VBAC assessment needed";
        }

        if ($patient->miscarriage >= 2) {
            $reasons[] = "History of " . $patient->miscarriage . " miscarriage(s)";
            $recommendations[] = "Specialist consultation for recurrent loss";
        }

        if ($request->gestational_age < 37 && $request->gestational_age >= 20) {
            $reasons[] = "Preterm gestation ({$request->gestational_age} weeks)";
            $recommendations[] = "Monitor for preterm labor signs";
        }

        // ======================
        // DOH OVERRIDE
        // ======================
        if (!empty($reasons)) {
            $risk = "HIGH";
        }

        // ======================
        // FINAL OUTPUT
        // ======================
        if ($risk === "HIGH") {
            $assessment = "High-risk pregnancy. Risk factors: " . implode(", ", array_slice($reasons, 0, 3));
            $recommendation = implode('. ', array_unique($recommendations)) . ". This is system-generated.";
            $nextVisit = now()->addDays(3);
        } else {
            $assessment = "Low-risk pregnancy.";
            $recommendation = "Continue routine prenatal care. This is system-generated.";
            $nextVisit = now()->addDays(30);
        }

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