<?php

namespace App\Support;

/**
 * Metadata-only registry of data-quality / documentation verification flags.
 *
 * These flags describe documentation or data-integrity items requiring
 * verification. They are NEVER clinical factors: they never enter
 * factor_evidence, never classify a pregnancy HIGH, and never change
 * LOW/HIGH/ASSESSMENT INCOMPLETE by themselves.
 */
class DataQualityFlagRegistry
{
    public const ACTIVE = 'ACTIVE';
    public const DEFERRED = 'DEFERRED';

    public const SEVERITY_INFO = 'INFO';
    public const SEVERITY_VERIFY = 'VERIFY';
    public const SEVERITY_IMPORTANT = 'IMPORTANT';

    public const SOURCE_PATIENT = 'PATIENT';
    public const SOURCE_ULTRASOUND = 'ULTRASOUND';
    public const SOURCE_MEDICAL_HISTORY = 'MEDICAL_HISTORY';
    public const SOURCE_BIRTH_PLAN = 'BIRTH_PLAN';

    /**
     * Active and documented (deferred) data-quality flags.
     */
    private const FLAGS = [
        'DQ-SOURCE-FUTURE-DATED' => [
            'label' => 'Ultrasound scan date is after the assessment date',
            'severity' => self::SEVERITY_VERIFY,
            'source_type' => self::SOURCE_ULTRASOUND,
            'source_fields' => ['scan_date'],
            'expected_condition' => 'The selected ultrasound scan date is not later than the assessment date.',
            'explanation' => 'The ultrasound record selected for this assessment has a scan date later than the assessment date.',
            'suggested_verification' => 'Confirm the ultrasound scan date and the assessment date.',
            'status' => self::ACTIVE,
        ],
        'DQ-ULTRASOUND-MISSING-FIELDS' => [
            'label' => 'Evaluated ultrasound fields are missing',
            'severity' => self::SEVERITY_VERIFY,
            'source_type' => self::SOURCE_ULTRASOUND,
            'source_fields' => ['presentation', 'amniotic_fluid', 'fetal_heartbeat'],
            'expected_condition' => 'All currently evaluated ultrasound fields are recorded.',
            'explanation' => 'One or more currently evaluated ultrasound fields are blank.',
            'suggested_verification' => 'Complete the missing ultrasound fields or confirm they were not assessed.',
            'status' => self::ACTIVE,
        ],
        'DQ-DUP-MEDICAL-HISTORY' => [
            'label' => 'More than one Medical History record',
            'severity' => self::SEVERITY_IMPORTANT,
            'source_type' => self::SOURCE_MEDICAL_HISTORY,
            'source_fields' => ['patient_id'],
            'expected_condition' => 'Exactly one active Medical History record per pregnancy.',
            'explanation' => 'More than one active Medical History record exists for this pregnancy.',
            'suggested_verification' => 'Review and reconcile the duplicate Medical History records.',
            'status' => self::ACTIVE,
        ],
        'DQ-DUP-BIRTH-PLAN' => [
            'label' => 'More than one Birth Plan record',
            'severity' => self::SEVERITY_IMPORTANT,
            'source_type' => self::SOURCE_BIRTH_PLAN,
            'source_fields' => ['patient_id'],
            'expected_condition' => 'Exactly one active Birth Plan record per pregnancy.',
            'explanation' => 'More than one active Birth Plan record exists for this pregnancy.',
            'suggested_verification' => 'Review and reconcile the duplicate Birth Plan records.',
            'status' => self::ACTIVE,
        ],

        // Deferred candidates — documented only, never evaluated in Sprint 13.
        'DQ-LMP-MISSING' => [
            'label' => 'Last menstrual period is missing',
            'severity' => self::SEVERITY_INFO,
            'source_type' => self::SOURCE_PATIENT,
            'source_fields' => ['lmp'],
            'expected_condition' => 'LMP is recorded for the pregnancy.',
            'explanation' => 'No LMP is recorded; workflow approval is required before this becomes a verification flag.',
            'suggested_verification' => null,
            'status' => self::DEFERRED,
        ],
        'DQ-EDD-MISSING' => [
            'label' => 'Estimated due date is missing',
            'severity' => self::SEVERITY_INFO,
            'source_type' => self::SOURCE_PATIENT,
            'source_fields' => ['edd'],
            'expected_condition' => 'EDD is recorded for the pregnancy.',
            'explanation' => 'No EDD is recorded; workflow approval is required before this becomes a verification flag.',
            'suggested_verification' => null,
            'status' => self::DEFERRED,
        ],
        'DQ-GA-DATE-MISMATCH' => [
            'label' => 'Gestational age inconsistent with dates',
            'severity' => self::SEVERITY_VERIFY,
            'source_type' => self::SOURCE_PATIENT,
            'source_fields' => ['lmp', 'gestational_age'],
            'expected_condition' => 'Gestational age is consistent with LMP within the clinic tolerance.',
            'explanation' => 'Deferred: the GA/LMP consistency check is currently a controller hard validation, not a flag.',
            'suggested_verification' => null,
            'status' => self::DEFERRED,
        ],
        'DQ-ULTRASOUND-STALE' => [
            'label' => 'Ultrasound may be stale',
            'severity' => self::SEVERITY_VERIFY,
            'source_type' => self::SOURCE_ULTRASOUND,
            'source_fields' => ['scan_date'],
            'expected_condition' => 'The selected ultrasound is current for the assessment.',
            'explanation' => 'Deferred: an age-based staleness threshold requires clinical approval.',
            'suggested_verification' => null,
            'status' => self::DEFERRED,
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::FLAGS);
    }

    /**
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
        return array_key_exists($code, self::FLAGS);
    }

    public static function isActive(string $code): bool
    {
        $metadata = self::FLAGS[$code] ?? null;

        return $metadata !== null && $metadata['status'] === self::ACTIVE;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function metadata(string $code): ?array
    {
        return self::FLAGS[$code] ?? null;
    }
}