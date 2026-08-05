<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\ValueObjects\AssessmentContext;
use App\ValueObjects\UltrasoundSnapshot;
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
    private AssessmentContextBuilder $assessmentContextBuilder;
    private ClinicalInteractionEngine $clinicalInteractionEngine;
    private AssessmentDataQualityService $assessmentDataQualityService;
    private DecisionTraceBuilder $decisionTraceBuilder;

    public function __construct(
        CompletenessValidator $completenessValidator,
        ClinicalRuleEngine $clinicalRuleEngine,
        MachineLearningService $machineLearningService,
        DecisionIntegrationService $decisionIntegrationService,
        BloodPressureAssessmentService $bloodPressureAssessmentService,
        BloodPressureFactorEvidenceMapper $bloodPressureFactorEvidenceMapper,
        AssessmentContextBuilder $assessmentContextBuilder,
        ClinicalInteractionEngine $clinicalInteractionEngine,
        AssessmentDataQualityService $assessmentDataQualityService,
        DecisionTraceBuilder $decisionTraceBuilder,
    ) {
        $this->completenessValidator = $completenessValidator;
        $this->clinicalRuleEngine = $clinicalRuleEngine;
        $this->machineLearningService = $machineLearningService;
        $this->decisionIntegrationService = $decisionIntegrationService;
        $this->bloodPressureAssessmentService = $bloodPressureAssessmentService;
        $this->bloodPressureFactorEvidenceMapper = $bloodPressureFactorEvidenceMapper;
        $this->assessmentContextBuilder = $assessmentContextBuilder;
        $this->clinicalInteractionEngine = $clinicalInteractionEngine;
        $this->assessmentDataQualityService = $assessmentDataQualityService;
        $this->decisionTraceBuilder = $decisionTraceBuilder;
    }

    public function assess(
        Patient $patient,
        array $inputs,
        ?array $repeatBpInputs = null,
        ?string $bpVerificationStatus = null,
        ?string $bpVerificationNote = null,
        ?string $assessmentDate = null,
        ?PrenatalVisit $visit = null,
        ?array $duplicateCounts = null
    ): AssessmentResult {
        // Select the exact ultrasound once (deterministically). The same record
        // is used to build the context and the controlled snapshot the rule
        // engine consumes, so the metadata can never disagree with the results.
        $ultrasound = $this->assessmentContextBuilder->latestUltrasound((int) $patient->id);
        $ultrasoundSnapshot = UltrasoundSnapshot::fromModel($ultrasound);

        // Build the reproducible context snapshot ONCE. It determines the exact
        // latest ultrasound used, and the same context is later persisted as
        // assessment metadata so the assessment is fully reproducible.
        $context = $this->assessmentContextBuilder->buildForPatient(
            $patient,
            $visit,
            $inputs,
            $assessmentDate,
            null,
            $ultrasound
        );

        $duplicateCounts = $duplicateCounts ?? [
            'medical_history' => $this->assessmentContextBuilder->activeMedicalHistoryCount((int) $patient->id),
            'birth_plan' => $this->assessmentContextBuilder->activeBirthPlanCount((int) $patient->id),
        ];

        $dataQualityFlags = $this->assessmentDataQualityService->evaluate($context, $duplicateCounts);

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

            $result = $this->decisionIntegrationService->decideUrgentBp(
                $missingRecords,
                [$bpResult['label']],
                $bpResult,
                $bpEvidence
            );

            return $this->finalize($result, $context, $dataQualityFlags, []);
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

            $result = $this->decisionIntegrationService->decide(
                $missingRecords,
                [],
                null,
                $bpResult['triggered'] ? $bpResult['urgency'] : null,
                $bpAlert,
                $bpEvidence
            );

            return $this->finalize($result, $context, $dataQualityFlags, []);
        }

        // ======================
        // STEP 2: EVALUATE DETERMINISTIC RULES (non-BP + BP-H)
        // ======================
        // The engine consumes the controlled ultrasound snapshot that the
        // context builder captured, never an Eloquent model and never a fresh
        // "latest" lookup, so rule output matches the persisted metadata.

        // Evaluate structured factors ONCE. The legacy reason strings are
        // derived from the same evidence so the two can never drift apart.
        $evidence = $this->clinicalRuleEngine->evaluateDetailed(
            $patient,
            $inputs,
            $ultrasoundSnapshot
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
            $result = $this->decisionIntegrationService->decide(
                [],
                $reasons,
                null,
                $bpResult['urgency'] ?? null,
                $bpResult['triggered'] ? $bpResult : null,
                $this->serializeEvidence($evidence)
            );

            return $this->finalize($result, $context, $dataQualityFlags, $evidence);
        }

        // ======================
        // STEP 3: EVALUATE ML OUTPUT (only when complete + no deterministic HIGH)
        // ======================
        $mlResult = $this->machineLearningService->predict(
            $patient,
            $inputs
        );

        $result = $this->decisionIntegrationService->decide(
            [],
            [],
            $mlResult,
            null,
            null,
            []
        );

        return $this->finalize($result, $context, $dataQualityFlags, []);
    }

    /**
     * Attach the reproducible metadata (context, interaction evidence,
     * data-quality flags, decision trace, versions, timestamp) to a decision.
     *
     * @param array<int, ClinicalFactorEvidence> $triggeredFactors
     */
    private function finalize(
        AssessmentResult $result,
        AssessmentContext $context,
        array $dataQualityFlags,
        array $triggeredFactors
    ): AssessmentResult {
        $interactionEvidence = $this->clinicalInteractionEngine->evaluate($context, $triggeredFactors);
        $assessedAt = now()->toDateTimeString();

        return new AssessmentResult(
            risk_level: $result->risk_level,
            assessment: $result->assessment,
            recommendation: $result->recommendation,
            reasons: $result->reasons,
            nextVisit: $result->nextVisit,
            decision_source: $result->decision_source,
            missing_records: $result->missing_records,
            rule_reasons: $result->rule_reasons,
            ml_prediction: $result->ml_prediction,
            ml_valid: $result->ml_valid,
            urgency: $result->urgency,
            bp_assessment: $result->bp_assessment,
            factor_evidence: $result->factor_evidence,
            context: $context->toArray(),
            interaction_evidence: $interactionEvidence,
            data_quality_flags: $dataQualityFlags,
            decision_trace: $this->decisionTraceBuilder->build($result, $assessedAt),
            assessed_at: $assessedAt,
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
