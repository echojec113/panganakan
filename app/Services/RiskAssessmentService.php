<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Ultrasound;
use App\ValueObjects\AssessmentResult;
use App\ValueObjects\ClinicalFactorEvidence;

class RiskAssessmentService
{
    private CompletenessValidator $completenessValidator;
    private ClinicalRuleEngine $clinicalRuleEngine;
    private MachineLearningService $machineLearningService;
    private DecisionIntegrationService $decisionIntegrationService;
    private BloodPressureAssessmentService $bloodPressureAssessmentService;
    private BloodPressureFactorEvidenceMapper $bloodPressureFactorEvidenceMapper;

    public function __construct(
        CompletenessValidator $completenessValidator,
        ClinicalRuleEngine $clinicalRuleEngine,
        MachineLearningService $machineLearningService,
        DecisionIntegrationService $decisionIntegrationService,
        BloodPressureAssessmentService $bloodPressureAssessmentService,
        BloodPressureFactorEvidenceMapper $bloodPressureFactorEvidenceMapper
    ) {
        $this->completenessValidator = $completenessValidator;
        $this->clinicalRuleEngine = $clinicalRuleEngine;
        $this->machineLearningService = $machineLearningService;
        $this->decisionIntegrationService = $decisionIntegrationService;
        $this->bloodPressureAssessmentService = $bloodPressureAssessmentService;
        $this->bloodPressureFactorEvidenceMapper = $bloodPressureFactorEvidenceMapper;
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

            $bpEvidence = $this->serializeEvidence(
                $this->bloodPressureFactorEvidenceMapper->toEvidence($bpResult)
            );

            return $this->decisionIntegrationService->decideUrgentBp(
                $missingRecords,
                [$bpResult['label']],
                $bpResult,
                $bpEvidence
            );
        }

        // ======================
        // STEP 1: CHECK REQUIRED RECORDS
        // ======================
        $missingRecords = $this->completenessValidator
            ->missingRequiredRecords($patient);

        if (!empty($missingRecords)) {
            $bpAlert = $bpResult['triggered'] ? $bpResult : null;

            // Only BP evidence may accompany a completeness result; no non-BP
            // deterministic evaluation happens before completeness passes.
            $bpEvidence = $this->serializeEvidence(
                $this->bloodPressureFactorEvidenceMapper->toEvidence($bpResult)
            );

            return $this->decisionIntegrationService->decide(
                $missingRecords,
                [],
                null,
                $bpResult['triggered'] ? $bpResult['urgency'] : null,
                $bpAlert,
                $bpEvidence
            );
        }

        // ======================
        // STEP 2: EVALUATE DETERMINISTIC RULES (non-BP + BP-H)
        // ======================
        $ultrasound = Ultrasound::where('patient_id', $patient->id)
            ->latest()
            ->first();

        // Evaluate structured factors ONCE. The legacy reason strings are
        // derived from the same evidence so the two can never drift apart.
        $evidence = $this->clinicalRuleEngine->evaluateDetailed(
            $patient,
            $inputs,
            $ultrasound
        );
        $reasons = array_map(
            static fn (ClinicalFactorEvidence $factor) => $factor->label,
            $evidence
        );

        if ($bpResult['triggered']) {
            $bpFactor = $this->bloodPressureFactorEvidenceMapper->toEvidence($bpResult);
            $reasons[] = $bpResult['label'];
            if ($bpFactor !== null) {
                $evidence[] = $bpFactor;
            }
        }

        if (!empty($reasons)) {
            return $this->decisionIntegrationService->decide(
                [],
                $reasons,
                null,
                $bpResult['urgency'] ?? null,
                $bpResult['triggered'] ? $bpResult : null,
                $this->serializeEvidence($evidence)
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
            null,
            []
        );
    }

    /**
     * Serialize evidence objects (and already-serialized arrays) into the
     * plain array-of-arrays contract expected by AssessmentResult persistence.
     *
     * @param ClinicalFactorEvidence|ClinicalFactorEvidence[]|array|null $evidence
     * @return array<int, array<string, mixed>>
     */
    private function serializeEvidence(mixed $evidence): array
    {
        if ($evidence instanceof ClinicalFactorEvidence) {
            return [$evidence->toArray()];
        }
        if (is_array($evidence)) {
            $serialized = [];
            foreach ($evidence as $item) {
                if ($item instanceof ClinicalFactorEvidence) {
                    $serialized[] = $item->toArray();
                } elseif (is_array($item)) {
                    $serialized[] = $item;
                }
            }
            return $serialized;
        }
        return [];
    }
}
