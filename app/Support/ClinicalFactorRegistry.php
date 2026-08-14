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
 *
 * Since Sprint 13 every factor also carries governance metadata. Only factors
 * whose governance state is ACTIVE may influence an assessment result.
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

    /** Governance states. */
    public const IMPLEMENTED = 'IMPLEMENTED';
    public const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';
    public const APPROVED = 'APPROVED';
    public const APPROVED_NOT_IMPLEMENTED = 'APPROVED_NOT_IMPLEMENTED';
    public const DRAFT = 'DRAFT';
    public const DEFERRED = 'DEFERRED';
    public const RECORD_ONLY = 'RECORD_ONLY';
    public const RETIRED = 'RETIRED';
    public const STANDALONE = 'STANDALONE';
    public const INTERACTION = 'INTERACTION';

    /**
     * Documentation-only grouping of database fields that are recorded but
     * never evaluated by any active rule. These are NOT factor codes.
     *
     * @var array<string, array<int, string>>
     */
    public const RECORD_ONLY_FIELD_GROUPS = [
        'WARNING_SYMPTOMS' => [
            'severe_headache',
            'visual_disturbance',
            'chest_pain',
            'shortness_breath',
        ],
        'PRENATAL_EXAMINATION' => [
            'temperature',
            'fundic_height',
            'fetal_heart_tone',
            'fetal_movement',
            'presenting_part',
            'uterine_activity',
            'cervical_dilation',
            'bag_of_water',
        ],
        'MEDICAL_HISTORY_BACKGROUND' => [
            'epilepsy',
            'hypertension',
            'asthma',
            'thyroid_disease',
            'heart_disease',
            'liver_disease',
            'smoking',
            'allergies',
            'drug_intake',
            'std_history',
            'breast_mass',
            'mental_health_condition',
            'other_specify',
        ],
        'ULTRASOUND_RECORD' => [
            'scan_date',
            'fetal_movement',
            'placenta_position',
            'gestational_age_scan',
            'estimated_fetal_weight',
            'report_image',
            'report_file',
            'remarks',
        ],
        'BIRTH_PLAN' => [
            'planned_visits',
            'deliver_in_clinic',
            'delivery_location',
            'transportation',
            'transport_cost',
            'payment_method',
            'saving_started',
            'birth_companion',
            'caregiver_home',
            'plan_more_children',
            'number_more_children',
            'knows_fp_method',
            'used_fp_before',
            'family_planning_method',
            'fp_source',
            'notes',
        ],
    ];

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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX AGE-Y (DOCU 4)',
                'notes' => 'Standalone maternal-demographics rule. Conditions unchanged since Sprint 12.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX AGE-A (DOCU 4)',
                'notes' => 'Standalone rule. Sprint 13 decision: kept exactly where it is; not re-represented as an interaction.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX BP-H (DOCU 4, DOCU 5)',
                'notes' => 'Thresholds and verification behavior defined in BloodPressureAssessmentService; unchanged.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX BP-URG (DOCU 4, DOCU 5)',
                'notes' => 'Pre-completeness urgent safety evaluation; missing records preserved. Unchanged.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX DM-01 (DOCU 4)',
                'notes' => 'Prenatal Visit checkbox is the source of truth (Sprint 11).',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX AN-01 (DOCU 4)',
                'notes' => 'Prenatal Visit checkbox is the source of truth (Sprint 11).',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX CS-01 (DOCU 4)',
                'notes' => 'Patient-level obstetric history factor.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX RM-03 (DOCU 4)',
                'notes' => 'Label placeholder {count} overridden at runtime by the rule engine.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX US-P01 (DOCU 4)',
                'notes' => 'Consumes the deterministic latest Ultrasound record selected by AssessmentContextBuilder.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX US-AF01 (DOCU 4)',
                'notes' => 'Consumes the deterministic latest Ultrasound record.',
            ],
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
            'governance' => [
                'implementation_status' => self::IMPLEMENTED,
                'approval_status' => self::APPROVED,
                'active' => true,
                'rule_version' => '1.0.0',
                'effective_from' => '2026-01-01',
                'standalone_or_interaction' => self::STANDALONE,
                'documentation_reference' => 'CLINICAL_FACTOR_MATRIX US-FH01 (DOCU 4)',
                'notes' => 'Consumes the deterministic latest Ultrasound record.',
            ],
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
     * Codes of factors whose governance state is ACTIVE.
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
     * Governance metadata for a factor code, or null for unknown codes.
     *
     * @return array<string, mixed>|null
     */
    public static function governance(string $code): ?array
    {
        $metadata = self::FACTORS[$code] ?? null;
        if ($metadata === null) {
            return null;
        }

        return $metadata['governance'] ?? [
            'implementation_status' => self::NOT_IMPLEMENTED,
            'approval_status' => self::DRAFT,
            'active' => false,
            'rule_version' => null,
            'effective_from' => null,
            'standalone_or_interaction' => self::STANDALONE,
            'documentation_reference' => null,
            'notes' => 'No governance metadata registered.',
        ];
    }

    /**
     * Whether a factor code is registered AND governance-marked active.
     * Unknown codes return false safely.
     */
    public static function isActive(string $code): bool
    {
        $governance = self::governance($code);

        return $governance !== null && ($governance['active'] ?? false) === true;
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