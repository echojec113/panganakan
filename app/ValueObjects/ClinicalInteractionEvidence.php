<?php

namespace App\ValueObjects;

use App\Support\ClinicalInteractionRegistry;

/**
 * Immutable structured evidence for one triggered clinical interaction.
 *
 * Never carries Eloquent models, patient-identifying information, or raw ML
 * output. Only ACTIVE interactions may produce evidence; Sprint 13 has none.
 */
class ClinicalInteractionEvidence
{
    private const APPROVED_KEYS = [
        'code',
        'label',
        'required_factor_codes',
        'observed_context',
        'decision_effect',
        'urgency',
        'explanation',
        'suggested_action',
        'rule_version',
    ];

    public readonly string $code;
    public readonly string $label;
    public readonly array $required_factor_codes;
    public readonly array $observed_context;
    public readonly ?string $decision_effect;
    public readonly ?string $urgency;
    public readonly string $explanation;
    public readonly ?string $suggested_action;
    public readonly ?string $rule_version;

    public function __construct(
        string $code,
        string $label,
        array $required_factor_codes,
        array $observed_context,
        ?string $decision_effect = null,
        ?string $urgency = null,
        string $explanation = '',
        ?string $suggested_action = null,
        ?string $rule_version = null,
    ) {
        if (!ClinicalInteractionRegistry::isRegistered($code)) {
            throw new \OutOfBoundsException("Unknown interaction code: {$code}");
        }

        $this->code = $code;
        $this->label = $label;
        $this->required_factor_codes = array_values($required_factor_codes);
        $this->observed_context = self::sanitizeSafeArray($observed_context);
        $this->decision_effect = $decision_effect;
        $this->urgency = $urgency;
        $this->explanation = $explanation;
        $this->suggested_action = $suggested_action;
        $this->rule_version = $rule_version;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'required_factor_codes' => $this->required_factor_codes,
            'observed_context' => $this->observed_context,
            'decision_effect' => $this->decision_effect,
            'urgency' => $this->urgency,
            'explanation' => $this->explanation,
            'suggested_action' => $this->suggested_action,
            'rule_version' => $this->rule_version,
        ];
    }

    /**
     * Normalize stored/passed interaction evidence to approved keys only.
     * Unknown keys are dropped; incomplete rows are rejected.
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

            if (!isset($row['code']) || $row['code'] === '' || !ClinicalInteractionRegistry::isRegistered($row['code'])) {
                continue;
            }
            if (!isset($row['label']) || $row['label'] === '') {
                continue;
            }

            $row['required_factor_codes'] = array_values(array_filter(
                isset($row['required_factor_codes']) && is_array($row['required_factor_codes'])
                    ? $row['required_factor_codes']
                    : [],
                static fn (mixed $code): bool => is_string($code)
            ));
            $row['observed_context'] = is_array($row['observed_context'] ?? null)
                ? self::sanitizeSafeArray($row['observed_context'])
                : [];

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    private static function sanitizeSafeArray(array $array): array
    {
        $safe = [];
        foreach ($array as $key => $value) {
            $safe[$key] = self::sanitizeValue($value);
        }
        return $safe;
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