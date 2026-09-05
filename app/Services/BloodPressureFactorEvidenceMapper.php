<?php

namespace App\Services;

use App\Support\ClinicalFactorRegistry;
use App\ValueObjects\ClinicalFactorEvidence;

/**
 * Narrow adapter that converts the existing structured bp_assessment array
 * from BloodPressureAssessmentService::assess() into a single
 * ClinicalFactorEvidence.
 *
 * - BP-H when reason_code = BP-H
 * - BP-URG when reason_code = BP-URG
 * - null (no evidence) when nothing was triggered
 * - null for unknown reason codes (fail safe; never invent evidence)
 *
 * The BloodPressureAssessmentService itself is left untouched: all thresholds,
 * verification statuses, and wording remain exactly as implemented in
 * Sprint 10.
 */
class BloodPressureFactorEvidenceMapper
{
    /**
     * @param array<string, mixed> $bpAssessment
     */
    public function toEvidence(array $bpAssessment): ?ClinicalFactorEvidence
    {
        $reasonCode = $bpAssessment['reason_code'] ?? null;

        if (!in_array($reasonCode, ['BP-H', 'BP-URG'], true)) {
            return null;
        }

        if (empty($bpAssessment['triggered'])) {
            return null;
        }

        if (!ClinicalFactorRegistry::isActive($reasonCode)) {
            return null;
        }

        $initial = $bpAssessment['initial_bp'] ?? null;
        $repeat = $bpAssessment['repeat_bp'] ?? null;

        $observed = [
            'initial' => $this->readingText($initial),
        ];
        if (is_array($repeat)) {
            $observed['repeat'] = $this->readingText($repeat);
            $observed['repeat_interpretation'] = $bpAssessment['repeat_interpretation'] ?? 'RECORDED';
        }

        $threshold = $bpAssessment['threshold']
            ?? (ClinicalFactorRegistry::metadata($reasonCode)['threshold_or_rule'] ?? null);

        return ClinicalFactorEvidence::forCode(
            $reasonCode,
            $observed,
            label: $bpAssessment['label'] ?? null,
            urgency: $bpAssessment['urgency'] ?? null,
            explanation: $bpAssessment['clinical_interpretation'] ?? null,
            suggestedAction: $bpAssessment['suggested_action'] ?? null,
            thresholdOrRule: $threshold,
        );
    }

    /**
     * @param mixed $reading
     */
    private function readingText(mixed $reading): string
    {
        if (!is_array($reading)) {
            return 'Not recorded';
        }

        $systolic = $reading['systolic'] ?? null;
        $diastolic = $reading['diastolic'] ?? null;

        if ($systolic === null || $diastolic === null) {
            return 'Not recorded';
        }

        return $systolic . '/' . $diastolic;
    }
}
