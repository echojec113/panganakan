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

    public function __construct(
        CompletenessValidator $completenessValidator,
        ClinicalRuleEngine $clinicalRuleEngine,
        MachineLearningService $machineLearningService,
        DecisionIntegrationService $decisionIntegrationService
    ) {
        $this->completenessValidator = $completenessValidator;
        $this->clinicalRuleEngine = $clinicalRuleEngine;
        $this->machineLearningService = $machineLearningService;
        $this->decisionIntegrationService = $decisionIntegrationService;
    }

    public function assess(Patient $patient, array $inputs): AssessmentResult
    {
        // ======================
        // STEP 1: CHECK REQUIRED RECORDS
        // ======================
        $missingRecords = $this->completenessValidator
            ->missingRequiredRecords($patient);

        if (!empty($missingRecords)) {
            return $this->decisionIntegrationService->decide(
                $missingRecords,
                [],
                null
            );
        }

        // ======================
        // STEP 2: EVALUATE RULE-BASED RISK FACTORS
        // ======================
        $ultrasound = Ultrasound::where('patient_id', $patient->id)
            ->latest()
            ->first();

        $reasons = $this->clinicalRuleEngine->evaluate(
            $patient,
            $inputs,
            $ultrasound
        );

        if (!empty($reasons)) {
            return $this->decisionIntegrationService->decide(
                [],
                $reasons,
                null
            );
        }

        // ======================
        // STEP 3: EVALUATE ML OUTPUT
        // ======================
        $mlResult = $this->machineLearningService->predict(
            $patient,
            $inputs
        );

        return $this->decisionIntegrationService->decide(
            [],
            [],
            $mlResult
        );
    }
}
