<?php

namespace App\Support;

/**
 * Metadata-only registry of clinical interaction rules.
 *
 * An interaction represents a multi-factor rule that may only become active
 * after clinical approval. Sprint 15 activates exactly three additive
 * explainability interactions (INT-BP-DM, INT-DM-AF, INT-CS-PRES). All other
 * candidates remain DRAFT or DEFERRED strictly for documentation.
 *
 * Interactions never classify or escalate on their own: they are additive
 * evidence layered on top of already-triggered ACTIVE standalone factors.
 */
class ClinicalInteractionRegistry
{
    public const ACTIVE = 'ACTIVE';
    public const APPROVED_NOT_IMPLEMENTED = 'APPROVED_NOT_IMPLEMENTED';
    public const DRAFT = 'DRAFT';
    public const DEFERRED = 'DEFERRED';
    public const RETIRED = 'RETIRED';

    /**
     * Registered interaction rules.
     *
     * Sprint 15 activates exactly three additive explainability interactions.
     * Every other candidate remains DRAFT or DEFERRED and is never evaluated.
     *
     * An optional `observed_value_conditions` map (path => expected value)
     * gates a candidate on a controlled, already-evaluated value from the
     * assessment context. Values are compared case-insensitively; a missing,
     * null, or malformed value never satisfies the condition.
     *
     * An optional `observed_context_keys` list (dotted context paths) declares
     * which controlled values the engine should preserve inside the interaction
     * evidence's observed_context (for explainability/reproducibility). Only the
     * declared paths are captured — never the full context, PII, or remarks.
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
        'INT-BP-DM' => [
            'label' => 'Elevated blood pressure with diabetes',
            'required_factor_codes' => ['BP-H', 'DM-01'],
            'status' => self::ACTIVE,
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'Both an elevated blood-pressure finding and diabetes were independently identified. The combination adds structured evidence supporting coordinated qualified clinical review; it does not diagnose pre-eclampsia and does not change the final risk classification or urgency.',
            'suggested_action' => 'Coordinate qualified clinical review of the elevated blood-pressure and diabetes findings.',
            'rule_version' => '1.1.0',
        ],
        'INT-DM-AF' => [
            'label' => 'Diabetes with high amniotic fluid',
            'required_factor_codes' => ['DM-01', 'US-AF01'],
            'status' => self::ACTIVE,
            'decision_effect' => null,
            'urgency' => null,
            'observed_value_conditions' => [
                'ultrasound_inputs.amniotic_fluid' => 'HIGH',
            ],
            'observed_context_keys' => ['ultrasound_inputs.amniotic_fluid'],
            'explanation' => 'A high amniotic-fluid finding and diabetes were identified alongside each other. This adds structured evidence for coordinated review of diabetes care and the ultrasound finding; it makes no causal claim, does not diagnose polyhydramnios cause or severity, and does not change the final risk classification or recommend delivery timing.',
            'suggested_action' => 'Coordinate qualified review of diabetes care and the high amniotic-fluid ultrasound finding.',
            'rule_version' => '1.1.0',
        ],
        'INT-CS-PRES' => [
            'label' => 'Previous cesarean with abnormal fetal presentation',
            'required_factor_codes' => ['CS-01', 'US-P01'],
            'status' => self::ACTIVE,
            'decision_effect' => null,
            'urgency' => null,
            'observed_context_keys' => ['ultrasound_inputs.presentation'],
            'explanation' => 'A previous cesarean section and an abnormal fetal presentation were both identified. Combined, these strengthen the need for hospital-level obstetric birth-planning and referral review; the CDSS does not determine cesarean, mode of birth, or VBAC eligibility.',
            'suggested_action' => 'Arrange hospital-level obstetric birth-planning and referral review.',
            'rule_version' => '1.1.0',
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
     * Codes whose governance status is ACTIVE.
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