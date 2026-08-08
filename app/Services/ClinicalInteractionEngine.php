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
 * An interaction is ADDITIVE evidence: it only combines factors that were
 * already produced by the standalone deterministic pipeline. It never
 * classifies, never escalates urgency, never invokes ML, never scores, and
 * never writes to the database.
 *
 * An optional `observed_value_conditions` map in the registry metadata gates a
 * candidate on a controlled, already-evaluated context value (compared
 * case-insensitively). Missing/null/malformed values never satisfy a
 * condition, so e.g. US-AF01 (Low) cannot trigger a HIGH-only interaction.
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

            if (!$this->passesObservedConditions($context, $metadata['observed_value_conditions'] ?? [])) {
                continue;
            }

            $evidence[] = new ClinicalInteractionEvidence(
                code: $code,
                label: $metadata['label'] ?? $code,
                required_factor_codes: $metadata['required_factor_codes'] ?? [],
                observed_context: $this->buildObservedContext($context, $metadata, $triggeredCodes),
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
     * The base snapshot carries only reproducible identifiers and statuses. When
     * the registry declares `observed_context_keys`, the corresponding controlled
     * values (e.g. the exact gated amniotic-fluid level) are preserved so the
     * interaction evidence explains what it observed without dumping the whole
     * AssessmentContext, ultrasound remarks, or patient-identifying data.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, bool> $triggeredCodes
     * @return array<string, mixed>
     */
    private function buildObservedContext(AssessmentContext $context, array $metadata, array $triggeredCodes): array
    {
        $observed = [
            'triggered_factor_codes' => array_keys($triggeredCodes),
            'gestational_age' => $context->gestational_age,
            'ultrasound_date' => $context->ultrasound_date,
            'patient_status' => $context->patient_status,
        ];

        $contextArray = $context->toArray();

        foreach (($metadata['observed_context_keys'] ?? []) as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }

            $value = $this->resolveContextPath($contextArray, $path);
            if ($value === null || $value === '') {
                continue;
            }

            $observed[$path] = strtoupper(trim((string) $value));
        }

        return $observed;
    }

    /**
     * Apply registry-declared observed-value gates against the controlled
     * assessment context.
     *
     * Each entry maps a dotted context path to an expected value, e.g.
     * ['ultrasound_inputs.amniotic_fluid' => 'HIGH']. Values are normalized to
     * uppercase so case does not decide a clinical gate. A missing, null,
     * empty, or malformed value never satisfies a condition: LOW amniotic
     * fluid (US-AF01 triggered as abnormal) must therefore NOT trigger the
     * HIGH-only INT-DM-AF interaction.
     *
     * @param array<string, mixed> $conditions
     */
    private function passesObservedConditions(AssessmentContext $context, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $contextArray = $context->toArray();

        foreach ($conditions as $path => $expected) {
            if (!is_string($path) || $path === '') {
                return false;
            }

            $actual = $this->resolveContextPath($contextArray, $path);
            if ($actual === null || $actual === '') {
                return false;
            }

            if (strtoupper(trim((string) $actual)) !== strtoupper(trim((string) $expected))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve a dotted path against a normalized context array.
     *
     * @param array<string, mixed> $array
     */
    private function resolveContextPath(array $array, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $array;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}