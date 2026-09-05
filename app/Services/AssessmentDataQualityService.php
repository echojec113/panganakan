<?php

namespace App\Services;

use App\Support\DataQualityFlagRegistry;
use App\ValueObjects\AssessmentContext;
use App\ValueObjects\DataQualityFlag;

/**
 * Evaluates objective, non-clinical documentation/data-quality verification
 * flags for an assessment.
 *
 * Flags are separate from clinical factor evidence, never alter
 * classification, and never trigger urgency. No medical timing thresholds,
 * stale-age calculations, diagnoses, or free-text technical output are used.
 */
class AssessmentDataQualityService
{
    private const EVALUATED_ULTRASOUND_FIELDS = [
        'presentation' => 'Presentation',
        'amniotic_fluid' => 'Amniotic fluid',
        'fetal_heartbeat' => 'Fetal heartbeat',
    ];

    private AssessmentContextBuilder $contextBuilder;

    public function __construct(AssessmentContextBuilder $contextBuilder)
    {
        $this->contextBuilder = $contextBuilder;
    }

    /**
     * Evaluate the ACTIVE data-quality flags for a context.
     *
     * @param AssessmentContext $context The assessment snapshot.
     * @param array{medical_history?: int, birth_plan?: int}|null $duplicateCounts
     *        Pre-computed active record counts so duplicate-detection queries
     *        are not repeated; when omitted the builder is queried once.
     * @return array<int, DataQualityFlag>
     */
    public function evaluate(AssessmentContext $context, ?array $duplicateCounts = null): array
    {
        $flags = [];

        foreach (DataQualityFlagRegistry::activeCodes() as $code) {
            $flag = match ($code) {
                'DQ-SOURCE-FUTURE-DATED' => $this->futureDatedFlag($context),
                'DQ-ULTRASOUND-MISSING-FIELDS' => $this->missingUltrasoundFieldsFlag($context),
                'DQ-DUP-MEDICAL-HISTORY' => $this->duplicateMedicalHistoryFlag($context, $duplicateCounts),
                'DQ-DUP-BIRTH-PLAN' => $this->duplicateBirthPlanFlag($context, $duplicateCounts),
                default => null,
            };

            if ($flag !== null) {
                $flags[] = $flag;
            }
        }

        return $flags;
    }

    private function futureDatedFlag(AssessmentContext $context): ?DataQualityFlag
    {
        if ($context->ultrasound_date === null || $context->assessment_date === null) {
            return null;
        }

        if ($context->ultrasound_date <= $context->assessment_date) {
            return null;
        }

        $metadata = DataQualityFlagRegistry::metadata('DQ-SOURCE-FUTURE-DATED');

        return new DataQualityFlag(
            code: 'DQ-SOURCE-FUTURE-DATED',
            label: $metadata['label'],
            severity: $metadata['severity'],
            source_type: $metadata['source_type'],
            source_fields: $metadata['source_fields'],
            observed_value: ['ultrasound_date' => $context->ultrasound_date, 'assessment_date' => $context->assessment_date],
            expected_condition: $metadata['expected_condition'],
            explanation: $metadata['explanation'],
            suggested_verification: $metadata['suggested_verification'],
        );
    }

    private function missingUltrasoundFieldsFlag(AssessmentContext $context): ?DataQualityFlag
    {
        if (!$context->ultrasound_present || $context->ultrasound_id === null) {
            return null;
        }

        // Evaluate from the captured context values only, so a later edit of the
        // Ultrasound row can never change the meaning of a persisted assessment.
        $inputs = $context->ultrasound_inputs;

        $missing = [];
        foreach (self::EVALUATED_ULTRASOUND_FIELDS as $field => $friendly) {
            $value = trim((string) ($inputs[$field] ?? ''));
            if ($value === '') {
                $missing[] = $friendly;
            }
        }

        if (empty($missing)) {
            return null;
        }

        $metadata = DataQualityFlagRegistry::metadata('DQ-ULTRASOUND-MISSING-FIELDS');

        return new DataQualityFlag(
            code: 'DQ-ULTRASOUND-MISSING-FIELDS',
            label: $metadata['label'],
            severity: $metadata['severity'],
            source_type: $metadata['source_type'],
            source_fields: $metadata['source_fields'],
            observed_value: ['missing_fields' => $missing],
            expected_condition: $metadata['expected_condition'],
            explanation: $metadata['explanation'],
            suggested_verification: $metadata['suggested_verification'],
        );
    }

    private function duplicateMedicalHistoryFlag(AssessmentContext $context, ?array $duplicateCounts): ?DataQualityFlag
    {
        $count = $duplicateCounts['medical_history']
            ?? $this->contextBuilder->activeMedicalHistoryCount($context->patient_id);

        if ($count <= 1) {
            return null;
        }

        $metadata = DataQualityFlagRegistry::metadata('DQ-DUP-MEDICAL-HISTORY');

        return new DataQualityFlag(
            code: 'DQ-DUP-MEDICAL-HISTORY',
            label: $metadata['label'],
            severity: $metadata['severity'],
            source_type: $metadata['source_type'],
            source_fields: $metadata['source_fields'],
            observed_value: ['active_record_count' => $count],
            expected_condition: $metadata['expected_condition'],
            explanation: $metadata['explanation'],
            suggested_verification: $metadata['suggested_verification'],
        );
    }

    private function duplicateBirthPlanFlag(AssessmentContext $context, ?array $duplicateCounts): ?DataQualityFlag
    {
        $count = $duplicateCounts['birth_plan']
            ?? $this->contextBuilder->activeBirthPlanCount($context->patient_id);

        if ($count <= 1) {
            return null;
        }

        $metadata = DataQualityFlagRegistry::metadata('DQ-DUP-BIRTH-PLAN');

        return new DataQualityFlag(
            code: 'DQ-DUP-BIRTH-PLAN',
            label: $metadata['label'],
            severity: $metadata['severity'],
            source_type: $metadata['source_type'],
            source_fields: $metadata['source_fields'],
            observed_value: ['active_record_count' => $count],
            expected_condition: $metadata['expected_condition'],
            explanation: $metadata['explanation'],
            suggested_verification: $metadata['suggested_verification'],
        );
    }
}