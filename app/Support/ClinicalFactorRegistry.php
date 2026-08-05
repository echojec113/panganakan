<?php

namespace App\Support;

/**
 * Metadata-only registry of every currently implemented deterministic
 * clinical factor code.
 *
 * The registry NEVER evaluates rules. It only describes the factors that the
 * clinical services are allowed to produce so that structured evidence can be
 * rendered consistently and so that unknown codes fail safely instead of
 * silently receiving invented clinical metadata.
 */
class ClinicalFactorRegistry
{
    public const CATEGORY_MATERNAL_DEMOGRAPHICS = 'MATERNAL_DEMOGRAPHICS';
    public const CATEGORY_VITAL_SIGNS = 'VITAL_SIGNS';
    public const CATEGORY_CURRENT_CONDITION = 'CURRENT_CONDITION';
    public const CATEGORY_OBSTETRIC_HISTORY = 'OBSTETRIC_HISTORY';
    public const CATEGORY_ULTRASOUND = 'ULTRASOUND';

    public const SOURCE_PATIENT = 'PATIENT';
    public const SOURCE_PRENATAL_VISIT = 'PRENATAL_VISIT';
    public const SOURCE_ULTRASOUND = 'ULTRASOUND';

    private const FACTORS = [
        'AGE-Y' => [
            'label' => 'Teenage pregnancy (under 19)',
            'category' => self::CATEGORY_MATERNAL_DEMOGRAPHICS,
            'source_type' => self::SOURCE_PATIENT,
            'source_fields' => ['age'],
            'threshold_or_rule' => 'Age < 19 years',
            'decision_effect' => 'HIGH',
            'urgency' => 'REVIEW_REQUIRED',
            'explanation' => "The patient's age indicates a need for age-sensitive clinical and social assessment.",
            'suggested_action' => 'Provide age-appropriate antenatal care and social support; refer for adolescent-focused services if available.',
        ],
        'AGE-A' => [
            'label' => 'Advanced maternal age (35+) and first pregnancy',
            'category' => self::CATEGORY_MATERNAL_DEMOGRAPHICS,
            'source_type' => self::SOURCE_PATIENT,
            'source_fields' => ['age', 'gravida', 'para'],
            'threshold_or_rule' => 'Age >= 35 years AND gravida = 1 AND para = 0',
            'decision_effect' => 'HIGH',
            'urgency' => 'REVIEW_REQUIRED',
            'explanation' => "The patient's age and first-pregnancy status indicate a need for individualized obstetric review.",
            'suggested_action' => 'Verify age, gravida, and para; schedule individualized obstetric review.',
        ],
        'BP-H' => [
            'label' => 'Elevated blood-pressure finding',
            'category' => self::CATEGORY_VITAL_SIGNS,
            'source_type' => self::SOURCE_PRENATAL_VISIT,
            'source_fields' => ['bp_sys', 'bp_dia', 'repeat_bp_sys', 'repeat_bp_dia'],
            'threshold_or_rule' => 'Systolic >= 140 mmHg or diastolic >= 90 mmHg',
            'decision_effect' => 'HIGH',
            'urgency' => 'PROMPT',
            'explanation' => "The recorded blood pressure reading is at or above the threshold that requires prompt qualified assessment.",
            'suggested_action' => 'Schedule prompt qualified blood-pressure assessment and review according to clinic protocol.',
        ],
        'BP-URG' => [
            'label' => 'Severe-range blood-pressure finding',
            'category' => self::CATEGORY_VITAL_SIGNS,
            'source_type' => self::SOURCE_PRENATAL_VISIT,
            'source_fields' => ['bp_sys', 'bp_dia', 'repeat_bp_sys', 'repeat_bp_dia'],
            'threshold_or_rule' => 'Systolic >= 160 mmHg or diastolic >= 110 mmHg',
            'decision_effect' => 'HIGH',
            'urgency' => 'URGENT_CLINICAL_REVIEW',
            'explanation' => 'The recorded reading met the severe-range screening threshold and requires urgent qualified clinical review.',
            'suggested_action' => 'Immediate qualified assessment and referral evaluation are recommended according to clinic protocol.',
        ],
        'DM-01' => [
            'label' => 'Diabetes',
            'category' => self::CATEGORY_CURRENT_CONDITION,
            'source_type' => self::SOURCE_PRENATAL_VISIT,
            'source_fields' => ['diabetes'],
            'threshold_or_rule' => 'Diabetes recorded present for the visit',
            'decision_effect' => 'HIGH',
            'urgency' => 'PROMPT',
            'explanation' => 'A recorded finding of diabetes indicates a need for medical and obstetric co-management.',
            'suggested_action' => 'Verify diabetes type and current management; plan medical and obstetric co-management.',
        ],
        'AN-01' => [
            'label' => 'Anemia',
            'category' => self::CATEGORY_CURRENT_CONDITION,
            'source_type' => self::SOURCE_PRENATAL_VISIT,
            'source_fields' => ['anemia'],
            'threshold_or_rule' => 'Anemia recorded present for the visit',
            'decision_effect' => 'HIGH',
            'urgency' => 'PROMPT',
            'explanation' => 'A recorded finding of anemia requires verification of severity, cause, and treatment.',
            'suggested_action' => 'Obtain complete blood count; confirm severity and treatment status.',
        ],
        'CS-01' => [
            'label' => 'Previous cesarean section',
            'category' => self::CATEGORY_OBSTETRIC_HISTORY,
            'source_type' => self::SOURCE_PATIENT,
            'source_fields' => ['previous_cs'],
            'threshold_or_rule' => 'Previous cesarean delivery recorded',
            'decision_effect' => 'HIGH',
            'urgency' => 'REVIEW_REQUIRED',
            'explanation' => 'A history of cesarean delivery requires hospital-level obstetric birth planning. This does not automatically mean another cesarean.',
            'suggested_action' => 'Verify number of previous cesareans; plan hospital-level obstetric birth care.',
        ],
        'RM-03' => [
            'label' => 'History of {count} miscarriage(s)',
            'category' => self::CATEGORY_OBSTETRIC_HISTORY,
            'source_type' => self::SOURCE_PATIENT,
            'source_fields' => ['miscarriage'],
            'threshold_or_rule' => 'Miscarriage count >= 3',
            'decision_effect' => 'HIGH',
            'urgency' => 'REVIEW_REQUIRED',
            'explanation' => 'A history of three or more previous losses indicates a need for specialist assessment and supportive antenatal care.',
            'suggested_action' => 'Verify number and timing of previous losses; arrange specialist assessment and supportive antenatal care.',
        ],
        'US-P01' => [
            'label' => 'Abnormal fetal presentation ({value})',
            'category' => self::CATEGORY_ULTRASOUND,
            'source_type' => self::SOURCE_ULTRASOUND,
            'source_fields' => ['presentation'],
            'threshold_or_rule' => 'Fetal presentation recorded Breech, Transverse, or Oblique',
            'decision_effect' => 'HIGH',
            'urgency' => 'REVIEW_REQUIRED',
            'explanation' => 'The recorded fetal presentation requires planning for hospital birth.',
            'suggested_action' => 'Verify presentation by qualified ultrasound; plan hospital birth.',
        ],
        'US-AF01' => [
            'label' => 'Amniotic fluid abnormality ({value})',
            'category' => self::CATEGORY_ULTRASOUND,
            'source_type' => self::SOURCE_ULTRASOUND,
            'source_fields' => ['amniotic_fluid'],
            'threshold_or_rule' => 'Amniotic fluid recorded Low or High',
            'decision_effect' => 'HIGH',
            'urgency' => 'REVIEW_REQUIRED',
            'explanation' => 'The recorded amniotic fluid finding requires clinical review.',
            'suggested_action' => 'Verify finding by qualified ultrasound; schedule clinical review.',
        ],
        'US-FH01' => [
            'label' => 'Fetal heartbeat abnormality ({value})',
            'category' => self::CATEGORY_ULTRASOUND,
            'source_type' => self::SOURCE_ULTRASOUND,
            'source_fields' => ['fetal_heartbeat'],
            'threshold_or_rule' => 'Fetal heartbeat recorded Weak, Abnormal, or Absent',
            'decision_effect' => 'HIGH',
            'urgency' => 'REVIEW_REQUIRED',
            'explanation' => 'The reported fetal heartbeat finding requires qualified verification and does not by itself confirm or exclude pregnancy loss.',
            'suggested_action' => 'Verify fetal heartbeat by qualified ultrasound; arrange qualified clinical review.',
        ],
    ];

    /**
     * Registered factor codes in stable display order.
     *
     * @return array<int, string>
     */
    public static function codes(): array
    {
        return array_keys(self::FACTORS);
    }

    /**
     * Whether a factor code is registered.
     */
    public static function isRegistered(string $code): bool
    {
        return array_key_exists($code, self::FACTORS);
    }

    /**
     * Metadata for a factor code, or null for unknown codes.
     *
     * Unknown codes return null so callers fail safely and never receive
     * invented clinical metadata.
     *
     * @return array<string, mixed>|null
     */
    public static function metadata(string $code): ?array
    {
        return self::FACTORS[$code] ?? null;
    }

    /**
     * All registered factor metadata keyed by code.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::FACTORS;
    }
}
