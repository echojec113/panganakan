<?php

namespace App\ValueObjects;

use App\Support\ClinicalFactorRegistry;
use OutOfBoundsException;

/**
 * Immutable structured representation of a single triggered deterministic
 * clinical factor.
 *
 * The object only carries staff-safe clinical evidence: factor code, friendly
 * label, category, source, observed value, rule/threshold summary, decision
 * effect, urgency, explanation, and suggested action. It never carries raw
 * model objects, patient-identifying information, or technical stack traces.
 */
class ClinicalFactorEvidence
{
    private const APPROVED_KEYS = [
        'code',
        'label',
        'category',
        'source_type',
        'source_fields',
        'observed_value',
        'threshold_or_rule',
        'decision_effect',
        'urgency',
        'explanation',
        'suggested_action',
    ];

    private const REQUIRED_KEYS = [
        'code',
        'label',
        'category',
        'source_type',
        'decision_effect',
    ];

    private const ACRONYMS = [
        'af' => 'AF',
        'bp' => 'BP',
        'bpm' => 'BPM',
        'cs' => 'CS',
        'fhr' => 'FHR',
        'ga' => 'GA',
        'hr' => 'HR',
        'lmp' => 'LMP',
        'ml' => 'ML',
        'ph' => 'PH',
        'us' => 'US',
    ];

    public readonly string $code;
    public readonly string $label;
    public readonly string $category;
    public readonly string $source_type;
    public readonly array $source_fields;
    public readonly mixed $observed_value;
    public readonly string $threshold_or_rule;
    public readonly string $decision_effect;
    public readonly ?string $urgency;
    public readonly string $explanation;
    public readonly ?string $suggested_action;

    /**
     * @throws OutOfBoundsException When the factor code is not registered.
     */
    public function __construct(
        string $code,
        string $label,
        string $category,
        string $source_type,
        array $source_fields,
        mixed $observed_value,
        string $threshold_or_rule,
        string $decision_effect,
        ?string $urgency = null,
        string $explanation = '',
        ?string $suggested_action = null,
    ) {
        // An unregistered code must never produce a valid evidence object:
        // clinical metadata can only be attached to a factor the registry
        // actually knows about.
        if (!ClinicalFactorRegistry::isRegistered($code)) {
            throw new OutOfBoundsException("Unknown clinical factor code: {$code}");
        }

        $this->code = $code;
        $this->label = $label;
        $this->category = $category;
        $this->source_type = $source_type;
        $this->source_fields = array_values($source_fields);
        $this->observed_value = self::sanitizeObserved($observed_value);
        $this->threshold_or_rule = $threshold_or_rule;
        $this->decision_effect = $decision_effect;
        $this->urgency = $urgency;
        $this->explanation = $explanation;
        $this->suggested_action = $suggested_action;
    }

    /**
     * Build structured evidence for a registered factor code, overlaying the
     * runtime observed value and any label/urgency/explanation/action overrides.
     *
     * Unknown codes throw OutOfBoundsException: a code must never silently
     * receive invented clinical metadata.
     *
     * @param mixed $observedValue
     */
    public static function forCode(
        string $code,
        mixed $observedValue,
        ?string $label = null,
        ?string $urgency = null,
        ?string $explanation = null,
        ?string $suggestedAction = null,
        ?string $thresholdOrRule = null
    ): self {
        $metadata = ClinicalFactorRegistry::metadata($code);
        if ($metadata === null) {
            throw new OutOfBoundsException("Unknown clinical factor code: {$code}");
        }

        return new self(
            code: $code,
            label: $label ?? $metadata['label'],
            category: $metadata['category'],
            source_type: $metadata['source_type'],
            source_fields: $metadata['source_fields'],
            observed_value: $observedValue,
            threshold_or_rule: $thresholdOrRule ?? $metadata['threshold_or_rule'],
            decision_effect: $metadata['decision_effect'],
            urgency: $urgency ?? $metadata['urgency'],
            explanation: $explanation ?? $metadata['explanation'],
            suggested_action: $suggestedAction ?? $metadata['suggested_action'],
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'category' => $this->category,
            'source_type' => $this->source_type,
            'source_fields' => $this->source_fields,
            'observed_value' => $this->observed_value,
            'threshold_or_rule' => $this->threshold_or_rule,
            'decision_effect' => $this->decision_effect,
            'urgency' => $this->urgency,
            'explanation' => $this->explanation,
            'suggested_action' => $this->suggested_action,
        ];
    }

    /**
     * Normalize stored/passed factor evidence into a list of plain
     * associative arrays with only the approved keys.
     *
     * Accepts a list of ClinicalFactorEvidence objects, a list of arrays, or
     * null. Unknown keys are dropped so views never render raw model data or
     * technical internals. Rows that are missing any of the required keys
     * (code, label, category, source_type, decision_effect) or that reference
     * an unregistered factor code are rejected entirely — a partial or
     * invented factor must never render as clinical evidence.
     *
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

            $row = [];
            foreach (self::APPROVED_KEYS as $key) {
                if (array_key_exists($key, $entry)) {
                    $row[$key] = $entry[$key];
                }
            }

            if (!self::isCompleteRow($row)) {
                continue;
            }

            $row['observed_value'] = self::sanitizeObserved($row['observed_value'] ?? null);

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * A row is only acceptable as evidence when every required key is present
     * and its code is registered. Unknown codes are rejected so an unregistered
     * factor can never be surfaced through the normalized contract.
     *
     * @param array<string, mixed> $row
     */
    private static function isCompleteRow(array $row): bool
    {
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
                return false;
            }
        }

        return ClinicalFactorRegistry::isRegistered((string) $row['code']);
    }

    /**
     * Recursively strip unsafe values from an observed value so the evidence
     * object never carries raw Eloquent models, arbitrary objects, resources,
     * closures, or unboundedly deep structures. Null, scalars, and recursive
     * arrays survive; everything else is replaced with the neutral "Recorded".
     */
    private static function sanitizeObserved(mixed $value, int $depth = 0): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($depth > 4) {
            return 'Recorded';
        }

        if (is_array($value)) {
            $safe = [];
            foreach ($value as $key => $item) {
                $safe[$key] = self::sanitizeObserved($item, $depth + 1);
            }
            return $safe;
        }

        return 'Recorded';
    }

    /**
     * Render a stored/associative key as a staff-facing label: underscores are
     * replaced with spaces, words are title-cased, and common medical acronyms
     * are uppercased (initial_bp → Initial BP).
     */
    private static function friendlyKey(string $key): string
    {
        $words = preg_split('/[_\-]+/', strtolower($key));
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));

        $tokens = [];
        foreach ($words as $word) {
            $tokens[] = self::ACRONYMS[$word] ?? ucfirst($word);
        }

        return implode(' ', $tokens);
    }

    /**
     * Render an observed value as a safe, staff-friendly display string.
     *
     * Nested arrays are flattened into "key: value" pairs so blood-pressure
     * evidence renders without dumping internal structure.
     */
    public static function displayObserved(mixed $value, int $depth = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            if ($depth > 2) {
                return 'Recorded';
            }
            $parts = [];
            foreach ($value as $key => $item) {
                if ($item === null || $item === '' || $item === []) {
                    continue;
                }
                if (is_int($key)) {
                    $parts[] = self::displayObserved($item, $depth + 1);
                } else {
                    $parts[] = self::friendlyKey((string) $key) . ': ' . self::displayObserved($item, $depth + 1);
                }
            }
            $text = implode(' · ', $parts);
            return $text !== '' ? $text : 'Recorded';
        }
        return 'Recorded';
    }
}
