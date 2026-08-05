<?php

namespace App\Services;

use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Support\AssessmentVersion;
use App\ValueObjects\AssessmentContext;
use App\ValueObjects\UltrasoundSnapshot;
use Carbon\CarbonImmutable;

/**
 * Pure-read builder that produces one reproducible AssessmentContext per
 * assessment. It performs no clinical classification, no database writes, no
 * ML calls, no referrals, and no audit logging.
 *
 * Deterministic latest-record ordering:
 * - Ultrasound: scan_date DESC, created_at DESC, id DESC
 * - Medical History / Birth Plan: created_at DESC, id DESC
 */
class AssessmentContextBuilder
{
    private const ALLOWED_INPUTS = [
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
     * Build a context for one assessment.
     *
     * @param Patient $patient The exact patient being assessed.
     * @param PrenatalVisit|null $visit The exact persisted visit, or null in
     *                                  pre-persistence (store()) mode.
     * @param array<string, mixed> $inputs Sanitized assessed values; unknown
     *                                     keys are dropped.
     * @param string|null $assessmentDate Stable date string used as the
     *                                    clinical/date anchor (context
     *                                    assessment_date) for source-date checks.
     * @param string|null $prePersistenceVisitDate Visit date before the visit is
     *                                             persisted (store() flows).
     * @param Ultrasound|null $ultrasound The exact ultrasound already selected
     *                                    by the caller. When null it is selected
     *                                    here (deterministically) so the caller
     *                                    and the metadata always agree on the
     *                                    same record.
     */
    public function buildForPatient(
        Patient $patient,
        ?PrenatalVisit $visit,
        array $inputs,
        ?string $assessmentDate = null,
        ?string $prePersistenceVisitDate = null,
        ?Ultrasound $ultrasound = null
    ): AssessmentContext {
        $assessmentDate = $assessmentDate ?? CarbonImmutable::now()->toDateString();

        $ultrasound = $ultrasound ?? $this->latestUltrasound((int) $patient->id);
        $snapshot = UltrasoundSnapshot::fromModel($ultrasound);
        $medicalHistory = $this->latestMedicalHistory((int) $patient->id);
        $birthPlan = $this->latestBirthPlan((int) $patient->id);

        $visitDate = $visit?->visit_date?->toDateString() ?? $prePersistenceVisitDate;
        $gestationalAge = isset($inputs['gestational_age']) && $inputs['gestational_age'] !== null
            ? (int) $inputs['gestational_age']
            : $visit?->gestational_age;

        return new AssessmentContext(
            patient_id: (int) $patient->id,
            patient_status: $patient->status,
            assessment_date: $assessmentDate,
            prenatal_visit_id: $visit?->id,
            prenatal_visit_date: $visitDate,
            medical_history_id: $medicalHistory?->id,
            medical_history_exists: $medicalHistory !== null,
            ultrasound_id: $ultrasound?->id,
            ultrasound_date: $ultrasound?->scan_date ? CarbonImmutable::parse($ultrasound->scan_date)->toDateString() : null,
            ultrasound_inputs: $snapshot !== null ? $snapshot->inputs() : [],
            birth_plan_id: $birthPlan?->id,
            birth_plan_exists: $birthPlan !== null,
            age: $patient->age !== null ? (int) $patient->age : null,
            gravida: $patient->gravida !== null ? (int) $patient->gravida : null,
            para: $patient->para !== null ? (int) $patient->para : null,
            previous_cs: $patient->previous_cs !== null ? (int) $patient->previous_cs : null,
            miscarriage: $patient->miscarriage !== null ? (int) $patient->miscarriage : null,
            lmp: $patient->lmp?->toDateString(),
            edd: $patient->edd?->toDateString(),
            gestational_age: $gestationalAge !== null ? (int) $gestationalAge : null,
            ultrasound_present: $ultrasound !== null,
            visit_inputs: $this->sanitizeInputs($inputs),
            source_summary: $this->buildSourceSummary($visit, $ultrasound, $medicalHistory, $birthPlan),
            context_version: AssessmentVersion::CONTEXT_VERSION,
        );
    }

    /**
     * Number of active (non-deleted) Medical History records for a pregnancy.
     * Used by data-quality duplicate detection so counts are computed once.
     */
    public function activeMedicalHistoryCount(int $patientId): int
    {
        return MedicalHistory::where('patient_id', $patientId)->count();
    }

    /**
     * Number of active (non-deleted) Birth Plan records for a pregnancy.
     * Used by data-quality duplicate detection so counts are computed once.
     */
    public function activeBirthPlanCount(int $patientId): int
    {
        return BirthPlan::where('patient_id', $patientId)->count();
    }

    /**
     * Deterministic latest Ultrasound: scan_date DESC, created_at DESC, id DESC.
     */
    public function latestUltrasound(int $patientId): ?Ultrasound
    {
        return Ultrasound::where('patient_id', $patientId)
            ->orderByDesc('scan_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Deterministic latest active Medical History: created_at DESC, id DESC.
     */
    public function latestMedicalHistory(int $patientId): ?MedicalHistory
    {
        return MedicalHistory::where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Deterministic latest active Birth Plan: created_at DESC, id DESC.
     */
    public function latestBirthPlan(int $patientId): ?BirthPlan
    {
        return BirthPlan::where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Keep only the approved assessed inputs; never carry free-text notes.
     *
     * @param array<string, mixed> $inputs
     * @return array<string, mixed>
     */
    private function sanitizeInputs(array $inputs): array
    {
        $safe = [];
        foreach (self::ALLOWED_INPUTS as $key) {
            if (array_key_exists($key, $inputs)) {
                $value = $inputs[$key];
                $safe[$key] = is_scalar($value) || $value === null ? $value : 'Recorded';
            }
        }
        return $safe;
    }

    /**
     * Friendly, non-PII summary of which source records informed this snapshot.
     *
     * @return array<string, mixed>
     */
    private function buildSourceSummary(
        ?PrenatalVisit $visit,
        ?Ultrasound $ultrasound,
        ?MedicalHistory $medicalHistory,
        ?BirthPlan $birthPlan
    ): array {
        return [
            'visit' => $visit ? 'Persisted visit included' : 'Pre-persistence assessment',
            'ultrasound' => $ultrasound
                ? 'Latest ultrasound by scan date'
                : 'No ultrasound record',
            'medical_history' => $medicalHistory ? 'Medical History present' : 'Medical History missing',
            'birth_plan' => $birthPlan ? 'Birth Plan present' : 'Birth Plan missing',
        ];
    }
}