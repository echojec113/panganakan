<?php

namespace App\ValueObjects;

use ArrayAccess;
use App\Support\AssessmentVersion;
use Carbon\CarbonImmutable;
use LogicException;
use OutOfBoundsException;

class AssessmentResult implements ArrayAccess
{
    private const APPROVED_KEYS = [
        'risk_level',
        'assessment',
        'recommendation',
        'reasons',
        'nextVisit',
        'decision_source',
        'missing_records',
        'rule_reasons',
        'ml_prediction',
        'ml_valid',
        'urgency',
        'bp_assessment',
        'factor_evidence',
        'context',
        'interaction_evidence',
        'data_quality_flags',
        'decision_trace',
        'versions',
        'assessed_at',
    ];

    public readonly string $risk_level;
    public readonly string $assessment;
    public readonly string $recommendation;
    public readonly array $reasons;
    public readonly CarbonImmutable $nextVisit;
    public readonly string $decision_source;
    public readonly array $missing_records;
    public readonly array $rule_reasons;
    public readonly ?string $ml_prediction;
    public readonly bool $ml_valid;
    public readonly ?string $urgency;
    public readonly ?array $bp_assessment;
    public readonly array $factor_evidence;
    public readonly ?array $context;
    public readonly array $interaction_evidence;
    public readonly array $data_quality_flags;
    public readonly array $decision_trace;
    public readonly array $versions;
    public readonly ?string $assessed_at;

    /**
     * An AssessmentResult always represents a freshly generated assessment, so
     * its version map is always the current value of AssessmentVersion::versions().
     * There is intentionally no way to inject a custom version map here: an
     * historical row must be read back from persistence, never forged in a
     * runtime value object.
     */
    public function __construct(
        string $risk_level,
        string $assessment,
        string $recommendation,
        array $reasons,
        CarbonImmutable $nextVisit,
        string $decision_source,
        array $missing_records,
        array $rule_reasons,
        ?string $ml_prediction,
        bool $ml_valid,
        ?string $urgency = null,
        ?array $bp_assessment = null,
        array $factor_evidence = [],
        ?array $context = null,
        array $interaction_evidence = [],
        array $data_quality_flags = [],
        array $decision_trace = [],
        ?string $assessed_at = null,
    ) {
        $this->risk_level = $risk_level;
        $this->assessment = $assessment;
        $this->recommendation = $recommendation;
        $this->reasons = $reasons;
        $this->nextVisit = $nextVisit;
        $this->decision_source = $decision_source;
        $this->missing_records = $missing_records;
        $this->rule_reasons = $rule_reasons;
        $this->ml_prediction = $ml_prediction;
        $this->ml_valid = $ml_valid;
        $this->urgency = $urgency;
        $this->bp_assessment = $bp_assessment;
        $this->factor_evidence = ClinicalFactorEvidence::normalizeList($factor_evidence);
        $this->context = $context !== null ? AssessmentContext::normalize($context) : null;
        $this->interaction_evidence = ClinicalInteractionEvidence::normalizeList($interaction_evidence);
        $this->data_quality_flags = DataQualityFlag::normalizeList($data_quality_flags);
        $this->decision_trace = DecisionTraceStep::normalizeList($decision_trace);
        $this->versions = AssessmentVersion::versions();
        $this->assessed_at = $assessed_at;
    }

    public function toArray(): array
    {
        return [
            'risk_level' => $this->risk_level,
            'assessment' => $this->assessment,
            'recommendation' => $this->recommendation,
            'reasons' => $this->reasons,
            'nextVisit' => $this->nextVisit,
            'decision_source' => $this->decision_source,
            'missing_records' => $this->missing_records,
            'rule_reasons' => $this->rule_reasons,
            'ml_prediction' => $this->ml_prediction,
            'ml_valid' => $this->ml_valid,
            'urgency' => $this->urgency,
            'bp_assessment' => $this->bp_assessment,
            'factor_evidence' => $this->factor_evidence,
            'context' => $this->context,
            'interaction_evidence' => $this->interaction_evidence,
            'data_quality_flags' => $this->data_quality_flags,
            'decision_trace' => $this->decision_trace,
            'versions' => $this->versions,
            'assessed_at' => $this->assessed_at,
        ];
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            return false;
        }

        return in_array($offset, self::APPROVED_KEYS, true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset) || !in_array($offset, self::APPROVED_KEYS, true)) {
            throw new OutOfBoundsException("Unknown property: {$offset}");
        }

        return $this->$offset;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('AssessmentResult is read-only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('AssessmentResult is read-only');
    }
}
