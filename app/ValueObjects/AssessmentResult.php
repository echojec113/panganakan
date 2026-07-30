<?php

namespace App\ValueObjects;

use ArrayAccess;
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
        bool $ml_valid
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
