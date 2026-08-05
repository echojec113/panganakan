<?php

namespace App\Services;

use App\ValueObjects\AssessmentResult;
use App\ValueObjects\DecisionTraceStep;

/**
 * Reconstructs the approved decision pipeline from an AssessmentResult.
 *
 * The trace is derived from the final result only (decision_source, urgency,
 * missing_records, bp_assessment, ml_prediction, ml_valid, factor_evidence,
 * interaction_evidence) so it can never disagree with the persisted
 * classification. It performs no scoring and no re-evaluation of clinical
 * rules, and it never emits stack traces, raw Python output, or technical
 * exceptions.
 *
 * Pipeline order:
 *   CONTEXT_BUILT -> URGENT_BP_CHECK -> COMPLETENESS_CHECK ->
 *   STANDALONE_RULE_EVALUATION -> INTERACTION_RULE_EVALUATION ->
 *   ML_EVALUATION -> FINAL_DECISION
 */
class DecisionTraceBuilder
{
    private const STEP_ORDER = [
        DecisionTraceStep::STEP_CONTEXT_BUILT,
        DecisionTraceStep::STEP_URGENT_BP_CHECK,
        DecisionTraceStep::STEP_COMPLETENESS_CHECK,
        DecisionTraceStep::STEP_STANDALONE_RULE,
        DecisionTraceStep::STEP_INTERACTION_RULE,
        DecisionTraceStep::STEP_ML,
        DecisionTraceStep::STEP_FINAL_DECISION,
    ];

    /**
     * @return array<int, DecisionTraceStep>
     */
    public function build(AssessmentResult $result, ?string $assessedAt = null): array
    {
        $steps = [];

        $assessedAt = $assessedAt ?? $result->assessed_at;
        $missing = $result->missing_records ?? [];
        $codes = $this->factorCodes($result);
        $interactionCodes = $this->interactionCodes($result);
        $bpReason = is_array($result->bp_assessment)
            ? ($result['bp_assessment']['reason_code'] ?? null)
            : null;
        $isBpUrg = $result->decision_source === 'RULE_BASED'
            && $result->urgency === 'URGENT_CLINICAL_REVIEW'
            && $bpReason === 'BP-URG';
        $shortCircuited = $isBpUrg || $result->decision_source === 'COMPLETENESS';

        foreach (self::STEP_ORDER as $code) {
            $steps[] = match ($code) {
                DecisionTraceStep::STEP_CONTEXT_BUILT => $this->contextStep($assessedAt),
                DecisionTraceStep::STEP_URGENT_BP_CHECK => $this->urgentBpStep($isBpUrg, $bpReason, $codes, $assessedAt),
                DecisionTraceStep::STEP_COMPLETENESS_CHECK => $this->completenessStep($isBpUrg, $missing, $bpReason, $assessedAt),
                DecisionTraceStep::STEP_STANDALONE_RULE => $this->standaloneStep($result, $shortCircuited, $codes, $assessedAt),
                DecisionTraceStep::STEP_INTERACTION_RULE => $this->interactionStep($shortCircuited, $interactionCodes, $assessedAt),
                DecisionTraceStep::STEP_ML => $this->mlStep($result, $shortCircuited, $assessedAt),
                DecisionTraceStep::STEP_FINAL_DECISION => $this->finalStep($result, $missing, $codes, $assessedAt),
            };
        }

        return $steps;
    }

    private function contextStep(?string $assessedAt): DecisionTraceStep
    {
        return new DecisionTraceStep(
            step_code: DecisionTraceStep::STEP_CONTEXT_BUILT,
            status: DecisionTraceStep::STATUS_COMPLETED,
            summary: 'Assessment context snapshot built from the exact records used by the engine.',
            assessed_at: $assessedAt,
        );
    }

    private function urgentBpStep(bool $isBpUrg, mixed $bpReason, array $codes, ?string $assessedAt): DecisionTraceStep
    {
        if ($isBpUrg) {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_URGENT_BP_CHECK,
                status: DecisionTraceStep::STATUS_TRIGGERED,
                summary: 'Severe-range blood pressure (BP-URG, >=160/110) triggered an urgent clinical review.',
                related_factor_codes: array_values(array_filter($codes, static fn (string $c) => $c === 'BP-URG')),
                assessed_at: $assessedAt,
            );
        }

        $summary = $bpReason === 'BP-H'
            ? 'Elevated blood pressure (BP-H) was noted but is not severe-range; it did not trigger urgent review.'
            : 'No severe-range blood-pressure finding; the urgent BP check completed without trigger.';

        return new DecisionTraceStep(
            step_code: DecisionTraceStep::STEP_URGENT_BP_CHECK,
            status: DecisionTraceStep::STATUS_COMPLETED,
            summary: $summary,
            assessed_at: $assessedAt,
        );
    }

    private function completenessStep(bool $isBpUrg, array $missing, mixed $bpReason, ?string $assessedAt): DecisionTraceStep
    {
        if ($isBpUrg) {
            // Severe BP overrides completeness: missing records are noted and
            // preserved, but they did not stop the urgent safety result.
            $summary = empty($missing)
                ? 'All required records were present.'
                : 'Required records were checked; missing records were preserved but did not block the urgent result.';
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_COMPLETENESS_CHECK,
                status: DecisionTraceStep::STATUS_COMPLETED,
                summary: $summary,
                missing_records: $missing,
                assessed_at: $assessedAt,
            );
        }

        if (empty($missing)) {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_COMPLETENESS_CHECK,
                status: DecisionTraceStep::STATUS_COMPLETED,
                summary: 'All required records were present.',
                assessed_at: $assessedAt,
            );
        }

        $bpNote = $bpReason === 'BP-H'
            ? ' The elevated-BP alert was preserved alongside the missing records.'
            : '';

        return new DecisionTraceStep(
            step_code: DecisionTraceStep::STEP_COMPLETENESS_CHECK,
            status: DecisionTraceStep::STATUS_BLOCKED,
            summary: 'Required records are missing, so the assessment stopped before deterministic rule evaluation.' . $bpNote,
            missing_records: $missing,
            assessed_at: $assessedAt,
        );
    }

    private function standaloneStep(AssessmentResult $result, bool $shortCircuited, array $codes, ?string $assessedAt): DecisionTraceStep
    {
        if ($shortCircuited) {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_STANDALONE_RULE,
                status: DecisionTraceStep::STATUS_SKIPPED,
                summary: 'Skipped because an earlier step already determined the result.',
                assessed_at: $assessedAt,
            );
        }

        if ($result->decision_source === 'RULE_BASED') {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_STANDALONE_RULE,
                status: DecisionTraceStep::STATUS_TRIGGERED,
                summary: 'One or more ACTIVE deterministic factors triggered, resolving to HIGH.',
                related_factor_codes: $codes,
                assessed_at: $assessedAt,
            );
        }

        return new DecisionTraceStep(
            step_code: DecisionTraceStep::STEP_STANDALONE_RULE,
            status: DecisionTraceStep::STATUS_COMPLETED,
            summary: 'No ACTIVE deterministic factor triggered; the assessment continued.',
            assessed_at: $assessedAt,
        );
    }

    private function interactionStep(bool $shortCircuited, array $interactionCodes, ?string $assessedAt): DecisionTraceStep
    {
        if ($shortCircuited) {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_INTERACTION_RULE,
                status: DecisionTraceStep::STATUS_SKIPPED,
                summary: 'Skipped because an earlier step already determined the result.',
                assessed_at: $assessedAt,
            );
        }

        return new DecisionTraceStep(
            step_code: DecisionTraceStep::STEP_INTERACTION_RULE,
            status: DecisionTraceStep::STATUS_COMPLETED,
            summary: 'No ACTIVE interactions evaluated; the interaction check completed.',
            related_interaction_codes: $interactionCodes,
            assessed_at: $assessedAt,
        );
    }

    private function mlStep(AssessmentResult $result, bool $shortCircuited, ?string $assessedAt): DecisionTraceStep
    {
        if ($shortCircuited || $result->decision_source === 'RULE_BASED') {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_ML,
                status: DecisionTraceStep::STATUS_SKIPPED,
                summary: 'Skipped because deterministic factors already established the result.',
                assessed_at: $assessedAt,
            );
        }

        if ($result->ml_valid) {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_ML,
                status: DecisionTraceStep::STATUS_COMPLETED,
                summary: "The ML model produced a valid prediction of {$result->ml_prediction}.",
                assessed_at: $assessedAt,
            );
        }

        return new DecisionTraceStep(
            step_code: DecisionTraceStep::STEP_ML,
            status: DecisionTraceStep::STATUS_BLOCKED,
            summary: 'The ML model did not produce a valid prediction, so the assessment remained incomplete.',
            assessed_at: $assessedAt,
        );
    }

    private function finalStep(AssessmentResult $result, array $missing, array $codes, ?string $assessedAt): DecisionTraceStep
    {
        $source = $this->sourceLabel($result);

        if ($result->risk_level === 'HIGH') {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_FINAL_DECISION,
                status: DecisionTraceStep::STATUS_TRIGGERED,
                summary: "Final result HIGH ({$source}).",
                related_factor_codes: $codes,
                assessed_at: $assessedAt,
            );
        }

        if ($result->risk_level === 'LOW') {
            return new DecisionTraceStep(
                step_code: DecisionTraceStep::STEP_FINAL_DECISION,
                status: DecisionTraceStep::STATUS_COMPLETED,
                summary: "Final result LOW ({$source}).",
                assessed_at: $assessedAt,
            );
        }

        return new DecisionTraceStep(
            step_code: DecisionTraceStep::STEP_FINAL_DECISION,
            status: DecisionTraceStep::STATUS_BLOCKED,
            summary: "Final result ASSESSMENT INCOMPLETE ({$source}).",
            missing_records: $missing,
            assessed_at: $assessedAt,
        );
    }

    private function sourceLabel(AssessmentResult $result): string
    {
        return match ($result->decision_source) {
            'COMPLETENESS' => 'completeness check',
            'RULE_BASED' => $result->urgency === 'URGENT_CLINICAL_REVIEW' ? 'urgent BP safety override' : 'deterministic rules',
            'MACHINE_LEARNING' => 'machine learning',
            'MACHINE_LEARNING_INVALID' => 'machine learning unavailable',
            default => 'assessment pipeline',
        };
    }

    /**
     * @return array<int, string>
     */
    private function factorCodes(AssessmentResult $result): array
    {
        $codes = [];
        foreach ($result->factor_evidence as $factor) {
            if (isset($factor['code']) && is_string($factor['code']) && $factor['code'] !== '') {
                $codes[] = $factor['code'];
            }
        }
        return array_values(array_unique($codes));
    }

    /**
     * @return array<int, string>
     */
    private function interactionCodes(AssessmentResult $result): array
    {
        $codes = [];
        foreach ($result->interaction_evidence as $item) {
            if (isset($item['code']) && is_string($item['code']) && $item['code'] !== '') {
                $codes[] = $item['code'];
            }
        }
        return array_values(array_unique($codes));
    }
}