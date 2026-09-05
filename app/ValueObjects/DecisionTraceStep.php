<?php

namespace App\ValueObjects;

use InvalidArgumentException;

/**
 * Immutable, ordered step in an assessment decision path.
 *
 * A trace represents the real executed pipeline and must never contradict the
 * final AssessmentResult. Each step uses the approved code set and status set
 * below, so downstream views can only ever render a governed, human-readable
 * path. Steps never include stack traces, raw Python output, technical
 * exceptions, PII, or free-text patient notes.
 */
class DecisionTraceStep
{
    /** Approved pipeline step codes, in the order they run. */
    public const STEP_CONTEXT_BUILT = 'CONTEXT_BUILT';
    public const STEP_URGENT_BP_CHECK = 'URGENT_BP_CHECK';
    public const STEP_COMPLETENESS_CHECK = 'COMPLETENESS_CHECK';
    public const STEP_STANDALONE_RULE = 'STANDALONE_RULE_EVALUATION';
    public const STEP_INTERACTION_RULE = 'INTERACTION_RULE_EVALUATION';
    public const STEP_ML = 'ML_EVALUATION';
    public const STEP_FINAL_DECISION = 'FINAL_DECISION';

    public const STEP_CODES = [
        self::STEP_CONTEXT_BUILT,
        self::STEP_URGENT_BP_CHECK,
        self::STEP_COMPLETENESS_CHECK,
        self::STEP_STANDALONE_RULE,
        self::STEP_INTERACTION_RULE,
        self::STEP_ML,
        self::STEP_FINAL_DECISION,
    ];

    /** Approved step statuses. */
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_TRIGGERED = 'TRIGGERED';
    public const STATUS_SKIPPED = 'SKIPPED';
    public const STATUS_BLOCKED = 'BLOCKED';

    public const STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_TRIGGERED,
        self::STATUS_SKIPPED,
        self::STATUS_BLOCKED,
    ];

    private const APPROVED_KEYS = [
        'step_code',
        'status',
        'summary',
        'related_factor_codes',
        'related_interaction_codes',
        'missing_records',
        'assessed_at',
    ];

    public readonly string $step_code;
    public readonly string $status;
    public readonly string $summary;
    public readonly array $related_factor_codes;
    public readonly array $related_interaction_codes;
    public readonly array $missing_records;
    public readonly ?string $assessed_at;

    public function __construct(
        string $step_code,
        string $status,
        string $summary,
        array $related_factor_codes = [],
        array $related_interaction_codes = [],
        array $missing_records = [],
        ?string $assessed_at = null,
    ) {
        if (!in_array($step_code, self::STEP_CODES, true)) {
            throw new InvalidArgumentException("Unknown decision trace step code: {$step_code}");
        }
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unknown decision trace status: {$status}");
        }

        $this->step_code = $step_code;
        $this->status = $status;
        $this->summary = $summary;
        $this->related_factor_codes = array_values(array_filter($related_factor_codes, 'is_string'));
        $this->related_interaction_codes = array_values(array_filter($related_interaction_codes, 'is_string'));
        $this->missing_records = array_values(array_filter($missing_records, 'is_string'));
        $this->assessed_at = $assessed_at;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'step_code' => $this->step_code,
            'status' => $this->status,
            'summary' => $this->summary,
            'related_factor_codes' => $this->related_factor_codes,
            'related_interaction_codes' => $this->related_interaction_codes,
            'missing_records' => $this->missing_records,
            'assessed_at' => $this->assessed_at,
        ];
    }

    /**
     * Normalize stored trace steps to approved keys. Malformed rows are
     * dropped; unknown keys are discarded; an invalid step code or status is
     * never allowed through.
     *
     * @param mixed $value
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeList(mixed $value): array
    {
        if ($value instanceof self) {
            return [$value->toArray()];
        }
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $entry) {
            if ($entry instanceof self) {
                $normalized[] = $entry->toArray();
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }

            if (!isset($entry['step_code'], $entry['status'])) {
                continue;
            }
            if (!in_array($entry['step_code'], self::STEP_CODES, true)) {
                continue;
            }
            if (!in_array($entry['status'], self::STATUSES, true)) {
                continue;
            }

            $row = [];
            foreach (self::APPROVED_KEYS as $key) {
                if (array_key_exists($key, $entry)) {
                    $row[$key] = $entry[$key];
                }
            }

            $row['summary'] = (string) ($row['summary'] ?? '');
            $row['related_factor_codes'] = array_values(array_filter(
                is_array($row['related_factor_codes'] ?? null) ? $row['related_factor_codes'] : [],
                'is_string'
            ));
            $row['related_interaction_codes'] = array_values(array_filter(
                is_array($row['related_interaction_codes'] ?? null) ? $row['related_interaction_codes'] : [],
                'is_string'
            ));
            $row['missing_records'] = array_values(array_filter(
                is_array($row['missing_records'] ?? null) ? $row['missing_records'] : [],
                'is_string'
            ));
            $row['assessed_at'] = isset($row['assessed_at']) && $row['assessed_at'] !== null
                ? (string) $row['assessed_at']
                : null;

            $normalized[] = $row;
        }

        return $normalized;
    }
}