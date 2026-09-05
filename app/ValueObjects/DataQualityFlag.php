<?php

namespace App\ValueObjects;

use App\Support\DataQualityFlagRegistry;

/**
 * Immutable structured record of one data-quality / documentation verification
 * flag. These are separate from clinical factor evidence and can never, by
 * themselves, classify a pregnancy HIGH.
 */
class DataQualityFlag
{
    private const APPROVED_KEYS = [
        'code',
        'label',
        'severity',
        'source_type',
        'source_fields',
        'observed_value',
        'expected_condition',
        'explanation',
        'suggested_verification',
    ];

    private const SEVERITIES = [
        DataQualityFlagRegistry::SEVERITY_INFO,
        DataQualityFlagRegistry::SEVERITY_VERIFY,
        DataQualityFlagRegistry::SEVERITY_IMPORTANT,
    ];

    public readonly string $code;
    public readonly string $label;
    public readonly string $severity;
    public readonly string $source_type;
    public readonly array $source_fields;
    public readonly mixed $observed_value;
    public readonly string $expected_condition;
    public readonly string $explanation;
    public readonly ?string $suggested_verification;

    public function __construct(
        string $code,
        string $label,
        string $severity,
        string $source_type,
        array $source_fields,
        mixed $observed_value,
        string $expected_condition,
        string $explanation,
        ?string $suggested_verification = null,
    ) {
        if (!DataQualityFlagRegistry::isRegistered($code)) {
            throw new \OutOfBoundsException("Unknown data-quality flag code: {$code}");
        }

        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new \OutOfBoundsException("Invalid data-quality severity: {$severity}");
        }

        $this->code = $code;
        $this->label = $label;
        $this->severity = $severity;
        $this->source_type = $source_type;
        $this->source_fields = array_values($source_fields);
        $this->observed_value = self::sanitizeValue($observed_value);
        $this->expected_condition = $expected_condition;
        $this->explanation = $explanation;
        $this->suggested_verification = $suggested_verification;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'severity' => $this->severity,
            'source_type' => $this->source_type,
            'source_fields' => $this->source_fields,
            'observed_value' => $this->observed_value,
            'expected_condition' => $this->expected_condition,
            'explanation' => $this->explanation,
            'suggested_verification' => $this->suggested_verification,
        ];
    }

    /**
     * Normalize stored data-quality flags to approved keys only. Unknown keys
     * are dropped; incomplete or unregistered rows are rejected.
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

            $row = [];
            foreach (self::APPROVED_KEYS as $key) {
                if (array_key_exists($key, $entry)) {
                    $row[$key] = $entry[$key];
                }
            }

            if (!isset($row['code']) || $row['code'] === '' || !DataQualityFlagRegistry::isRegistered($row['code'])) {
                continue;
            }
            if (!isset($row['label']) || $row['label'] === '' || !isset($row['severity'])) {
                continue;
            }
            if (!in_array($row['severity'], self::SEVERITIES, true)) {
                continue;
            }

            $row['observed_value'] = self::sanitizeValue($row['observed_value'] ?? null);

            $normalized[] = $row;
        }

        return $normalized;
    }

    private static function sanitizeValue(mixed $value, int $depth = 0): mixed
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
                $safe[$key] = self::sanitizeValue($item, $depth + 1);
            }
            return $safe;
        }
        return 'Recorded';
    }
}