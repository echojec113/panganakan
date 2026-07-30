<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Ultrasound;
use App\ValueObjects\AssessmentResult;

class RiskAssessmentService
{
    private CompletenessValidator $completenessValidator;
    private ClinicalRuleEngine $clinicalRuleEngine;
    private MachineLearningService $machineLearningService;
    private DecisionIntegrationService $decisionIntegrationService;
    private BloodPressureAssessmentService $bloodPressureAssessmentService;

    public function __construct(
        CompletenessValidator $completenessValidator,
        ClinicalRuleEngine $clinicalRuleEngine,
        MachineLearningService $machineLearningService,
        DecisionIntegrationService $decisionIntegrationService,
        BloodPressureAssessmentService $bloodPressureAssessmentService
    ) {
        $this->completenessValidator = $completenessValidator;
        $this->clinicalRuleEngine = $clinicalRuleEngine;
        $this->machineLearningService = $machineLearningService;
        $this->decisionIntegrationService = $decisionIntegrationService;
        $this->bloodPressureAssessmentService = $bloodPressureAssessmentService;
    }

    public function assess(
        Patient $patient,
        array $inputs,
        ?array $repeatBpInputs = null,
        ?string $bpVerificationStatus = null,
        ?string $bpVerificationNote = null
    ): AssessmentResult {
        $bpResult = $this->bloodPressureAssessmentService->assess(
            isset($inputs['bp_sys']) ? (int) $inputs['bp_sys'] : null,
            isset($inputs['bp_dia']) ? (int) $inputs['bp_dia'] : null,
            $repeatBpInputs['bp_sys'] ?? null,
            $repeatBpInputs['bp_dia'] ?? null,
            $bpVerificationStatus,
            $bpVerificationNote
        );

        // ======================
        // PRIORITY 0: BP-URG — pre-completeness urgent safety evaluation
        // ======================
        if ($bpResult['triggered'] && $bpResult['reason_code'] === 'BP-URG') {
            $missingRecords = $this->completenessValidator
                ->missingRequiredRecords($patient);

            return $this->decisionIntegrationService->decide(
                $missingRecords,
                ['Severe-range blood-pressure finding'],
                null,
                'URGENT_CLINICAL_REVIEW',
                $bpResult
            );
        }

        // ======================
        // STEP 1: CHECK REQUIRED RECORDS
        // ======================
        $missingRecords = $this->completenessValidator
            ->missingRequiredRecords($patient);

        if (!empty($missingRecords)) {
            $bpAlert = $bpResult['triggered'] ? $bpResult : null;
            return $this->decisionIntegrationService->decide(
                $missingRecords,
                [],
                null,
                $bpResult['triggered'] ? $bpResult['urgency'] : null,
                $bpAlert
            );
        }

        // ======================
        // STEP 2: EVALUATE DETERMINISTIC RULES (non-BP + BP-H)
        // ======================
        $ultrasound = Ultrasound::where('patient_id', $patient->id)
            ->latest()
            ->first();

        $reasons = $this->clinicalRuleEngine->evaluate(
            $patient,
            $inputs,
            $ultrasound
        );

        if ($bpResult['triggered']) {
            $reasons[] = $bpResult['label'];
        }

        if (!empty($reasons)) {
            return $this->decisionIntegrationService->decide(
                [],
                $reasons,
                null,
                $bpResult['urgency'] ?? null,
                $bpResult['triggered'] ? $bpResult : null
            );
        }

        // ======================
        // STEP 3: EVALUATE ML OUTPUT (only when complete + no deterministic HIGH)
        // ======================
        $mlResult = $this->machineLearningService->predict(
            $patient,
            $inputs
        );

        return $this->decisionIntegrationService->decide(
            [],
            [],
            $mlResult,
            null,
            null
        );
    }
}
