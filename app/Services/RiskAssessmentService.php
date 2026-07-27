<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Ultrasound;
use App\Models\MedicalHistory;
use App\Models\BirthPlan;
use Illuminate\Support\Facades\Log;

class RiskAssessmentService
{
    public function assess(Patient $patient, array $inputs): array
    {
        // ======================
        // STEP 1: CHECK REQUIRED RECORDS
        // ======================
        $hasMedicalHistory = MedicalHistory::where('patient_id', $patient->id)->exists();
        $hasUltrasound = Ultrasound::where('patient_id', $patient->id)->exists();
        $hasBirthPlan = BirthPlan::where('patient_id', $patient->id)->exists();

        $missingRecords = [];
        if (!$hasMedicalHistory) {
            $missingRecords[] = 'Medical History';
        }
        if (!$hasUltrasound) {
            $missingRecords[] = 'Ultrasound Record';
        }
        if (!$hasBirthPlan) {
            $missingRecords[] = 'Birth Plan';
        }

        if (!empty($missingRecords)) {
            $missingList = implode(', ', $missingRecords);
            return [
                'risk_level' => 'ASSESSMENT INCOMPLETE',
                'assessment' => "Assessment incomplete. The following required records are missing: {$missingList}.",
                'recommendation' => 'Complete all required records (' . $missingList . ') before final risk classification. This is system-generated and is not a medical diagnosis.',
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
        if ($inputs['bp_sys'] >= 140 || $inputs['bp_dia'] >= 90) {
            $reasons[] = "Hypertension (BP: {$inputs['bp_sys']}/{$inputs['bp_dia']})";

            if ($inputs['bp_sys'] >= 160 || $inputs['bp_dia'] >= 110) {
                $reasons[] = "Severe hypertension (BP: {$inputs['bp_sys']}/{$inputs['bp_dia']})";
            }
        }

        // Medical conditions
        if ($inputs['diabetes'] == 1) {
            $reasons[] = "Diabetes";
        }

        if ($inputs['anemia'] == 1) {
            $reasons[] = "Anemia";
        }

        if ($patient->previous_cs == 1) {
            $reasons[] = "Previous cesarean section";
        }

        if ($patient->miscarriage >= 3) {
            $reasons[] = "History of " . $patient->miscarriage . " miscarriage(s)";
        }

        // Ultrasound findings
        $ultrasound = Ultrasound::where('patient_id', $patient->id)
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
        $mlInputs = [
            $patient->age,
            $patient->gravida,
            $patient->para,
            $inputs['bp_sys'],
            $inputs['bp_dia'],
            $inputs['weight'],
            $inputs['gestational_age'],
            $inputs['hypertension'],
            $inputs['diabetes'],
            $patient->previous_cs,
            $patient->miscarriage,
            $inputs['anemia']
        ];

        $configuredPythonPath = trim(env('PYTHON_PATH', ''));
        if ($configuredPythonPath !== '' && file_exists($configuredPythonPath)) {
            $python = escapeshellarg($configuredPythonPath);
        } else {
            if ($configuredPythonPath !== '') {
                Log::warning('Configured PYTHON_PATH does not exist, falling back to python on PATH: ' . $configuredPythonPath);
            }
            $python = 'python';
        }

        $script = escapeshellarg(base_path('maternal-risk-ml/predict.py'));
        $inputsStr = implode(' ', array_map('escapeshellarg', $mlInputs));
        $command = "{$python} {$script} {$inputsStr} 2>&1";

        Log::info('ML COMMAND: ' . $command . ' | Patient ID: ' . $patient->id);

        $output = shell_exec($command);
        $rawMlOutput = trim((string) $output);

        $outputLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawMlOutput)));
        $parsedMlOutput = $outputLines ? end($outputLines) : '';

        Log::info('ML RAW OUTPUT: ' . $rawMlOutput . ' | PARSED OUTPUT: ' . $parsedMlOutput . ' | Patient ID: ' . $patient->id);

        $mlRisk = null;
        $mlRiskValid = false;

        if ($parsedMlOutput !== '' && !preg_match('/error|exception|traceback|failed|unable/i', $rawMlOutput)) {
            $normalizedMlRisk = strtoupper($parsedMlOutput);
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
}
