<?php

namespace App\ValueObjects;

use App\Support\AssessmentVersion;
use OutOfBoundsException;

/**
 * Immutable, reproducible snapshot of the information used for ONE assessment.
 *
 * Carries only scalar values, date strings, booleans, record IDs, and safe
 * arrays. It never carries Eloquent models, patient name/address/email/contact
 * information, raw Request data, or raw ML output. Unknown keys are dropped on
 * hydration and unsafe objects/resources are replaced with neutral values.
 */
class AssessmentContext
{
    private const ALLOWED_KEYS = [
        'patient_id',
        'patient_status',
        'assessment_date',
        'prenatal_visit_id',
        'prenatal_visit_date',
        'medical_history_id',
        'medical_history_exists',
        'ultrasound_id',
        'ultrasound_date',
        'ultrasound_inputs',
        'birth_plan_id',
        'birth_plan_exists',
        'age',
        'gravida',
        'para',
        'previous_cs',
        'miscarriage',
        'lmp',
        'edd',
        'gestational_age',
        'ultrasound_present',
        'visit_inputs',
        'source_summary',
        'context_version',
    ];

    /**
     * The only visit inputs permitted in the context. Free-text patient
     * notes are never included.
     */
    private const ALLOWED_VISIT_INPUTS = [
        'bp_sys',
        'bp_dia',
        'repeat_bp_sys',
        'repeat_bp_dia',
        'weight',
        'gestational_age',
        'hypertension',
        'diabetes',
        'anemia',
        'bp_verification_status',
    ];

    /**
     * The only ultrasound findings permitted in the context — the exact values
     * the deterministic rules evaluate. Anything outside these keys is dropped.
     */
    private const ALLOWED_ULTRASOUND_INPUTS = [
        'presentation',
        'amniotic_fluid',
        'fetal_heartbeat',
    ];

    public readonly int $patient_id;
    public readonly ?string $patient_status;
    public readonly ?string $assessment_date;
    public readonly ?int $prenatal_visit_id;
    public readonly ?string $prenatal_visit_date;
    public readonly ?int $medical_history_id;
    public readonly bool $medical_history_exists;
    public readonly ?int $ultrasound_id;
    public readonly ?string $ultrasound_date;
    public readonly array $ultrasound_inputs;
    public readonly ?int $birth_plan_id;
    public readonly bool $birth_plan_exists;
    public readonly ?int $age;
    public readonly ?int $gravida;
    public readonly ?int $para;
    public readonly ?int $previous_cs;
    public readonly ?int $miscarriage;
    public readonly ?string $lmp;
    public readonly ?string $edd;
    public readonly ?int $gestational_age;
    public readonly bool $ultrasound_present;
    public readonly array $visit_inputs;
    public readonly array $source_summary;
    public readonly int $context_version;

    public function __construct(
        int $patient_id,
        ?string $patient_status = null,
        ?string $assessment_date = null,
        ?int $prenatal_visit_id = null,
        ?string $prenatal_visit_date = null,
        ?int $medical_history_id = null,
        bool $medical_history_exists = false,
        ?int $ultrasound_id = null,
        ?string $ultrasound_date = null,
        array $ultrasound_inputs = [],
        ?int $birth_plan_id = null,
        bool $birth_plan_exists = false,
        ?int $age = null,
        ?int $gravida = null,
        ?int $para = null,
        ?int $previous_cs = null,
        ?int $miscarriage = null,
        ?string $lmp = null,
        ?string $edd = null,
        ?int $gestational_age = null,
        bool $ultrasound_present = false,
        array $visit_inputs = [],
        array $source_summary = [],
        int $context_version = AssessmentVersion::CONTEXT_VERSION,
    ) {
        $this->patient_id = $patient_id;
        $this->patient_status = $patient_status;
        $this->assessment_date = $assessment_date;
        $this->prenatal_visit_id = $prenatal_visit_id;
        $this->prenatal_visit_date = $prenatal_visit_date;
        $this->medical_history_id = $medical_history_id;
        $this->medical_history_exists = $medical_history_exists;
        $this->ultrasound_id = $ultrasound_id;
        $this->ultrasound_date = $ultrasound_date;
        $this->ultrasound_inputs = self::sanitizeUltrasoundInputs($ultrasound_inputs);
        $this->birth_plan_id = $birth_plan_id;
        $this->birth_plan_exists = $birth_plan_exists;
        $this->age = $age;
        $this->gravida = $gravida;
        $this->para = $para;
        $this->previous_cs = $previous_cs;
        $this->miscarriage = $miscarriage;
        $this->lmp = $lmp;
        $this->edd = $edd;
        $this->gestational_age = $gestational_age;
        $this->ultrasound_present = $ultrasound_present;
        $this->visit_inputs = self::sanitizeVisitInputs($visit_inputs);
        $this->source_summary = self::sanitizeSafeArray($source_summary);
        $this->context_version = $context_version;
    }

    /**
     * Controlled serialization contract. Only approved keys, plain values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patient_id,
            'patient_status' => $this->patient_status,
            'assessment_date' => $this->assessment_date,
            'prenatal_visit_id' => $this->prenatal_visit_id,
            'prenatal_visit_date' => $this->prenatal_visit_date,
            'medical_history_id' => $this->medical_history_id,
            'medical_history_exists' => $this->medical_history_exists,
            'ultrasound_id' => $this->ultrasound_id,
            'ultrasound_date' => $this->ultrasound_date,
            'ultrasound_inputs' => $this->ultrasound_inputs,
            'birth_plan_id' => $this->birth_plan_id,
            'birth_plan_exists' => $this->birth_plan_exists,
            'age' => $this->age,
            'gravida' => $this->gravida,
            'para' => $this->para,
            'previous_cs' => $this->previous_cs,
            'miscarriage' => $this->miscarriage,
            'lmp' => $this->lmp,
            'edd' => $this->edd,
            'gestational_age' => $this->gestational_age,
            'ultrasound_present' => $this->ultrasound_present,
            'visit_inputs' => $this->visit_inputs,
            'source_summary' => $this->source_summary,
            'context_version' => $this->context_version,
        ];
    }

    /**
     * Normalize a stored/passed context array: drop unknown keys, whitelist
     * visit inputs, and strip unsafe values. Used at the persistence boundary.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $safe = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (array_key_exists($key, $data)) {
                $safe[$key] = $data[$key];
            }
        }

        if (array_key_exists('visit_inputs', $safe)) {
            $safe['visit_inputs'] = self::sanitizeVisitInputs(is_array($safe['visit_inputs']) ? $safe['visit_inputs'] : []);
        }

        if (array_key_exists('ultrasound_inputs', $safe)) {
            $safe['ultrasound_inputs'] = self::sanitizeUltrasoundInputs(is_array($safe['ultrasound_inputs']) ? $safe['ultrasound_inputs'] : []);
        }

        foreach ($safe as $key => $value) {
            $safe[$key] = self::sanitizeValue($value);
        }

        return $safe;
    }

    /**
     * Rehydrate an AssessmentContext from a normalized array.
     *
     * Unknown keys are dropped; a missing patient_id throws because a context
     * without a patient is meaningless.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $normalized = self::normalize($data);

        if (!isset($normalized['patient_id'])) {
            throw new OutOfBoundsException('AssessmentContext requires a patient_id');
        }

        return new self(
            patient_id: (int) $normalized['patient_id'],
            patient_status: isset($normalized['patient_status']) && $normalized['patient_status'] !== null ? (string) $normalized['patient_status'] : null,
            assessment_date: isset($normalized['assessment_date']) && $normalized['assessment_date'] !== null ? (string) $normalized['assessment_date'] : null,
            prenatal_visit_id: isset($normalized['prenatal_visit_id']) && $normalized['prenatal_visit_id'] !== null ? (int) $normalized['prenatal_visit_id'] : null,
            prenatal_visit_date: isset($normalized['prenatal_visit_date']) && $normalized['prenatal_visit_date'] !== null ? (string) $normalized['prenatal_visit_date'] : null,
            medical_history_id: isset($normalized['medical_history_id']) && $normalized['medical_history_id'] !== null ? (int) $normalized['medical_history_id'] : null,
            medical_history_exists: (bool) ($normalized['medical_history_exists'] ?? false),
            ultrasound_id: isset($normalized['ultrasound_id']) && $normalized['ultrasound_id'] !== null ? (int) $normalized['ultrasound_id'] : null,
            ultrasound_date: isset($normalized['ultrasound_date']) && $normalized['ultrasound_date'] !== null ? (string) $normalized['ultrasound_date'] : null,
            ultrasound_inputs: isset($normalized['ultrasound_inputs']) && is_array($normalized['ultrasound_inputs']) ? $normalized['ultrasound_inputs'] : [],
            birth_plan_id: isset($normalized['birth_plan_id']) && $normalized['birth_plan_id'] !== null ? (int) $normalized['birth_plan_id'] : null,
            birth_plan_exists: (bool) ($normalized['birth_plan_exists'] ?? false),
            age: isset($normalized['age']) && $normalized['age'] !== null ? (int) $normalized['age'] : null,
            gravida: isset($normalized['gravida']) && $normalized['gravida'] !== null ? (int) $normalized['gravida'] : null,
            para: isset($normalized['para']) && $normalized['para'] !== null ? (int) $normalized['para'] : null,
            previous_cs: isset($normalized['previous_cs']) && $normalized['previous_cs'] !== null ? (int) $normalized['previous_cs'] : null,
            miscarriage: isset($normalized['miscarriage']) && $normalized['miscarriage'] !== null ? (int) $normalized['miscarriage'] : null,
            lmp: isset($normalized['lmp']) && $normalized['lmp'] !== null ? (string) $normalized['lmp'] : null,
            edd: isset($normalized['edd']) && $normalized['edd'] !== null ? (string) $normalized['edd'] : null,
            gestational_age: isset($normalized['gestational_age']) && $normalized['gestational_age'] !== null ? (int) $normalized['gestational_age'] : null,
            ultrasound_present: (bool) ($normalized['ultrasound_present'] ?? false),
            visit_inputs: isset($normalized['visit_inputs']) && is_array($normalized['visit_inputs']) ? $normalized['visit_inputs'] : [],
            source_summary: isset($normalized['source_summary']) && is_array($normalized['source_summary']) ? $normalized['source_summary'] : [],
            context_version: (int) ($normalized['context_version'] ?? AssessmentVersion::CONTEXT_VERSION),
        );
    }

    /**
     * Keep only the approved visit inputs; drop everything else.
     *
     * @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private static function sanitizeVisitInputs(array $inputs): array
    {
        $safe = [];
        foreach (self::ALLOWED_VISIT_INPUTS as $key) {
            if (array_key_exists($key, $inputs)) {
                $safe[$key] = self::sanitizeValue($inputs[$key]);
            }
        }
        return $safe;
    }

    /**
     * Keep only the three approved ultrasound findings; drop everything else.
     *
     * @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private static function sanitizeUltrasoundInputs(array $inputs): array
    {
        $safe = [];
        foreach (self::ALLOWED_ULTRASOUND_INPUTS as $key) {
            if (array_key_exists($key, $inputs)) {
                $safe[$key] = self::sanitizeValue($inputs[$key]);
            }
        }
        return $safe;
    }

    /**
     * Strip unsafe values: null/scalars/plain recursive arrays survive;
     * objects, resources, and closures are replaced with a neutral value.
     */
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

    /**
     * Sanitize an arbitrary array through the same recursive value filter.
     *
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
}