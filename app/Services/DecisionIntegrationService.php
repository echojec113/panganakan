<?php

namespace App\Services;

use App\ValueObjects\AssessmentResult;

class DecisionIntegrationService
{
    /**
     * Explicit urgent safety path for severe-range BP (BP-URG).
     * This path is evaluated BEFORE the generic completeness branch so that a
     * severe-range blood-pressure finding always resolves to HIGH +
     * URGENT_CLINICAL_REVIEW even when required records are missing. Missing
     * records are preserved so data gaps are not hidden.
     */
    public function decideUrgentBp(
        array $missingRecords,
        array $ruleReasons,
        ?array $bpAssessment
    ): AssessmentResult {
        // The severe-range BP finding is a safety reason that must always be
        // surfaced, even if the caller supplied no other rule reasons.
        $bpLabel = $bpAssessment['label'] ?? null;
        if ($bpLabel !== null) {
            $ruleReasons[] = $bpLabel;
        }

        $uniqueReasons = array_values(array_unique($ruleReasons));
        $assessment = "High-risk pregnancy. Risk factors identified: " . implode(", ", array_slice($uniqueReasons, 0, 3));
        if (count($uniqueReasons) > 3) {
            $assessment .= " and " . (count($uniqueReasons) - 3) . " more factor(s).";
        }

        return new AssessmentResult(
            risk_level: 'HIGH',
            assessment: $assessment,
            recommendation: 'Immediate qualified clinical review and referral evaluation are recommended. This is system-generated and is not a medical diagnosis.',
            reasons: $uniqueReasons,
            nextVisit: now()->toImmutable()->addDays(3),
            decision_source: 'RULE_BASED',
            missing_records: $missingRecords,
            rule_reasons: $uniqueReasons,
            ml_prediction: null,
            ml_valid: false,
            urgency: 'URGENT_CLINICAL_REVIEW',
            bp_assessment: $bpAssessment,
        );
    }

    public function decide(
        array $missingRecords,
        array $ruleReasons,
        ?array $mlResult = null,
        ?string $urgency = null,
        ?array $bpAssessment = null
    ): AssessmentResult {
        if ($bpAssessment !== null && ($bpAssessment['reason_code'] ?? null) === 'BP-URG') {
            return $this->decideUrgentBp($missingRecords, $ruleReasons, $bpAssessment);
        }

        if (!empty($missingRecords)) {
            $missingList = implode(', ', $missingRecords);
            return new AssessmentResult(
                risk_level: 'ASSESSMENT INCOMPLETE',
                assessment: "Assessment incomplete. The following required records are missing: {$missingList}.",
                recommendation: 'Complete all required records (' . $missingList . ') before final risk classification. This is system-generated and is not a medical diagnosis.',
                reasons: [],
                nextVisit: now()->toImmutable()->addDays(30),
                decision_source: 'COMPLETENESS',
                missing_records: $missingRecords,
                rule_reasons: [],
                ml_prediction: null,
                ml_valid: false,
                urgency: $urgency,
                bp_assessment: $bpAssessment,
            );
        }

        if (!empty($ruleReasons)) {
            $uniqueReasons = array_values(array_unique($ruleReasons));
            $assessment = "High-risk pregnancy. Risk factors identified: " . implode(", ", array_slice($uniqueReasons, 0, 3));
            if (count($uniqueReasons) > 3) {
                $assessment .= " and " . (count($uniqueReasons) - 3) . " more factor(s).";
            }

            return new AssessmentResult(
                risk_level: 'HIGH',
                assessment: $assessment,
                recommendation: 'Referral or clinic staff review is recommended. This is system-generated and is not a medical diagnosis.',
                reasons: $uniqueReasons,
                nextVisit: now()->toImmutable()->addDays(3),
                decision_source: 'RULE_BASED',
                missing_records: [],
                rule_reasons: $uniqueReasons,
                ml_prediction: null,
                ml_valid: false,
                urgency: $urgency,
                bp_assessment: $bpAssessment,
            );
        }

        if ($mlResult !== null && ($mlResult['prediction'] ?? null) === 'HIGH') {
            return new AssessmentResult(
                risk_level: 'HIGH',
                assessment: 'High-risk pregnancy. The ML assessment indicated high risk.',
                recommendation: 'Referral or clinic staff review is recommended. This is system-generated and is not a medical diagnosis.',
                reasons: [],
                nextVisit: now()->toImmutable()->addDays(3),
                decision_source: 'MACHINE_LEARNING',
                missing_records: [],
                rule_reasons: [],
                ml_prediction: 'HIGH',
                ml_valid: true,
                urgency: null,
                bp_assessment: null,
            );
        }

        if ($mlResult !== null && ($mlResult['valid'] ?? false) && ($mlResult['prediction'] ?? null) === 'LOW') {
            return new AssessmentResult(
                risk_level: 'LOW',
                assessment: 'Low-risk pregnancy. No rule-based risk factors identified.',
                recommendation: 'Continue routine prenatal checkups as advised by clinic personnel. This is system-generated and is not a medical diagnosis.',
                reasons: [],
                nextVisit: now()->toImmutable()->addDays(30),
                decision_source: 'MACHINE_LEARNING',
                missing_records: [],
                rule_reasons: [],
                ml_prediction: 'LOW',
                ml_valid: true,
                urgency: null,
                bp_assessment: null,
            );
        }

        return new AssessmentResult(
            risk_level: 'ASSESSMENT INCOMPLETE',
            assessment: 'Assessment incomplete. Missing or invalid information prevented final risk classification.',
            recommendation: 'Complete the missing record(s) before final risk classification. This is system-generated and is not a medical diagnosis.',
            reasons: [],
            nextVisit: now()->toImmutable()->addDays(30),
            decision_source: 'MACHINE_LEARNING_INVALID',
            missing_records: [],
            rule_reasons: [],
            ml_prediction: $mlResult['prediction'] ?? null,
            ml_valid: $mlResult['valid'] ?? false,
            urgency: null,
            bp_assessment: null,
        );
    }
}
