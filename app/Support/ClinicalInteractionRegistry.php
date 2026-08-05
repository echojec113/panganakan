<?php

namespace App\Support;

/**
 * Metadata-only registry of clinical interaction rules.
 *
 * An interaction represents a multi-factor rule that may only become active
 * after clinical approval. Sprint 13 ships ZERO ACTIVE interactions: the
 * registry, evidence object, and engine exist as infrastructure only.
 *
 * Candidates are registered as DRAFT or DEFERRED strictly for documentation.
 */
class ClinicalInteractionRegistry
{
    public const ACTIVE = 'ACTIVE';
    public const APPROVED_NOT_IMPLEMENTED = 'APPROVED_NOT_IMPLEMENTED';
    public const DRAFT = 'DRAFT';
    public const DEFERRED = 'DEFERRED';
    public const RETIRED = 'RETIRED';

    /**
     * Candidate interactions, all DRAFT or DEFERRED. None is evaluated in
     * Sprint 13. This array is documentation only.
     */
    private const INTERACTIONS = [
        'INT-US-PRESENTATION-GA' => [
            'label' => 'Abnormal fetal presentation with gestational-age context',
            'required_factor_codes' => ['US-P01'],
            'status' => self::DRAFT,
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'Documentation candidate: combine abnormal presentation with approved gestational-age context.',
            'suggested_action' => null,
            'rule_version' => null,
        ],
        'INT-WARNING-BP' => [
            'label' => 'Warning symptom with elevated or severe blood pressure',
            'required_factor_codes' => ['BP-H', 'BP-URG'],
            'status' => self::DEFERRED,
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'Documentation candidate: warning-symptom evaluation is deferred and requires clinical approval.',
            'suggested_action' => null,
            'rule_version' => null,
        ],
        'INT-SYMPTOM-CONDITION' => [
            'label' => 'Current symptom with related background condition',
            'required_factor_codes' => [],
            'status' => self::DEFERRED,
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'Documentation candidate: symptom-to-background-condition mapping is not implemented.',
            'suggested_action' => null,
            'rule_version' => null,
        ],
        'INT-ANEMIA-LAB' => [
            'label' => 'Confirmed laboratory anemia severity',
            'required_factor_codes' => ['AN-01'],
            'status' => self::DRAFT,
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'Documentation candidate: laboratory Hb severity is not yet a data source.',
            'suggested_action' => null,
            'rule_version' => null,
        ],
        'INT-PERSISTENT-FINDING' => [
            'label' => 'Persistent finding across multiple visits',
            'required_factor_codes' => [],
            'status' => self::DEFERRED,
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'Documentation candidate: multi-visit persistence logic requires clinical approval.',
            'suggested_action' => null,
            'rule_version' => null,
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::INTERACTIONS);
    }

    /**
     * Codes whose governance status is ACTIVE. Empty in Sprint 13 by design.
     *
     * @return array<int, string>
     */
    public static function activeCodes(): array
    {
        return array_values(array_filter(
            self::codes(),
            static fn (string $code): bool => self::isActive($code)
        ));
    }

    public static function isRegistered(string $code): bool
    {
        return array_key_exists($code, self::INTERACTIONS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function metadata(string $code): ?array
    {
        return self::INTERACTIONS[$code] ?? null;
    }

    public static function status(string $code): ?string
    {
        $metadata = self::INTERACTIONS[$code] ?? null;

        return $metadata === null ? null : $metadata['status'];
    }

    public static function isActive(string $code): bool
    {
        $metadata = self::INTERACTIONS[$code] ?? null;

        return $metadata !== null && $metadata['status'] === self::ACTIVE;
    }
}