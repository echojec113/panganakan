<?php

namespace App\Services;

use App\Support\ClinicalInteractionRegistry;
use App\ValueObjects\AssessmentContext;
use App\ValueObjects\ClinicalFactorEvidence;
use App\ValueObjects\ClinicalInteractionEvidence;

/**
 * Evaluates registered clinical interactions against the assessment context
 * and triggered factors.
 *
 * Sprint 13 ships ZERO ACTIVE interactions, so evaluate() always returns an
 * empty list. The engine performs no database query, no write, and computes no
 * score: it never escalates based only on the number of triggered factors.
 */
class ClinicalInteractionEngine
{
    /**
     * @param AssessmentContext $context The reproducible assessment snapshot.
     * @param array<int, ClinicalFactorEvidence> $triggeredFactors Evidence of
     *                                                             ACTIVE factors already triggered.
     * @return array<int, ClinicalInteractionEvidence>
     */
    public function evaluate(AssessmentContext $context, array $triggeredFactors): array
    {
        $triggeredCodes = [];
        foreach ($triggeredFactors as $factor) {
            $triggeredCodes[$factor->code] = true;
        }

        $evidence = [];

        foreach (ClinicalInteractionRegistry::activeCodes() as $code) {
            $metadata = ClinicalInteractionRegistry::metadata($code);
            if ($metadata === null) {
                continue;
            }

            $required = array_filter(
                $metadata['required_factor_codes'] ?? [],
                static fn (string $factorCode): bool => isset($triggeredCodes[$factorCode])
            );

            $allPresent = count($required) === count($metadata['required_factor_codes'] ?? []);
            if (!$allPresent) {
                continue;
            }

            $evidence[] = new ClinicalInteractionEvidence(
                code: $code,
                label: $metadata['label'] ?? $code,
                required_factor_codes: $metadata['required_factor_codes'] ?? [],
                observed_context: $this->buildObservedContext($context, $triggeredCodes),
                decision_effect: $metadata['decision_effect'] ?? null,
                urgency: $metadata['urgency'] ?? null,
                explanation: $metadata['explanation'] ?? '',
                suggested_action: $metadata['suggested_action'] ?? null,
                rule_version: $metadata['rule_version'] ?? null,
            );
        }

        return $evidence;
    }

    /**
     * Build a safe, context-only observation snapshot. No PII, no models.
     *
     * @return array<string, mixed>
     */
    private function buildObservedContext(AssessmentContext $context, array $triggeredCodes): array
    {
        return [
            'triggered_factor_codes' => array_keys($triggeredCodes),
            'gestational_age' => $context->gestational_age,
            'ultrasound_date' => $context->ultrasound_date,
            'patient_status' => $context->patient_status,
        ];
    }
}