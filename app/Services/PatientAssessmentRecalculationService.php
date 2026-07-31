<?php

namespace App\Services;

use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use Illuminate\Support\Facades\Log;

class PatientAssessmentRecalculationService
{
    private RiskAssessmentService $riskAssessmentService;

    public function __construct(RiskAssessmentService $riskAssessmentService)
    {
        $this->riskAssessmentService = $riskAssessmentService;
    }

    /**
     * Recalculate only ASSESSMENT INCOMPLETE prenatal visits of a patient.
     * Called when Medical History, Ultrasound, or Birth Plan is created/updated.
     *
     * Safety boundaries:
     * - Missing patient or delivered patient -> no-op.
     * - Requires all three records: Medical History, Ultrasound, Birth Plan.
     * - Already finalized HIGH or LOW visits are never rewritten.
     * - For each recalculated incomplete visit, the stored repeat-BP pair,
     *   verification status/note, BP assessment metadata, and an existing
     *   next_visit_date are preserved.
     *
     * @param int $patientId
     * @return void
     */
    public function recalculateIncompleteVisits(int $patientId): void
    {
        $patient = Patient::find($patientId);
        if (!$patient) {
            return;
        }

        if ($patient->isDelivered()) {
            return;
        }

        // Check if all required records exist
        $hasMedicalHistory = MedicalHistory::where('patient_id', $patientId)->exists();
        $hasUltrasound = Ultrasound::where('patient_id', $patientId)->exists();
        $hasBirthPlan = BirthPlan::where('patient_id', $patientId)->exists();

        // Only recalculate if all required records are now complete
        if (!$hasMedicalHistory || !$hasUltrasound || !$hasBirthPlan) {
            return;
        }

        // Recalculate only visits that have not been finalized yet. HIGH and LOW
        // assessments are historical and must not be retroactively rewritten.
        $visits = PrenatalVisit::where('patient_id', $patientId)
            ->where('risk_level', 'ASSESSMENT INCOMPLETE')
            ->get();

        foreach ($visits as $visit) {
            $repeatBpInputs = null;
            if ($visit->repeat_bp_sys !== null && $visit->repeat_bp_dia !== null) {
                $repeatBpInputs = [
                    'bp_sys' => (int) $visit->repeat_bp_sys,
                    'bp_dia' => (int) $visit->repeat_bp_dia,
                ];
            }

            $storedBpAssessment = is_array($visit->bp_assessment) ? $visit->bp_assessment : [];
            $storedVerificationNote = $storedBpAssessment['verification_note'] ?? null;

            $riskAssessment = $this->riskAssessmentService->assess(
                $patient,
                [
                    'bp_sys' => $visit->bp_sys,
                    'bp_dia' => $visit->bp_dia,
                    'weight' => $visit->weight,
                    'gestational_age' => $visit->gestational_age,
                    'hypertension' => $visit->hypertension,
                    'diabetes' => $visit->diabetes,
                    'anemia' => $visit->anemia,
                ],
                $repeatBpInputs,
                $visit->bp_verification_status,
                $storedVerificationNote
            );

            $visit->update([
                'risk_level' => $riskAssessment['risk_level'],
                'assessment' => $riskAssessment['assessment'],
                'recommendation' => $riskAssessment['recommendation'],
                'risk_reasons' => $riskAssessment['reasons'],
                'decision_source' => $riskAssessment['decision_source'] ?? null,
                'missing_records' => $riskAssessment['missing_records'] ?? [],
                'rule_reasons' => $riskAssessment['rule_reasons'] ?? [],
                'ml_prediction' => $riskAssessment['ml_prediction'] ?? null,
                'ml_valid' => $riskAssessment['ml_valid'] ?? false,
                'next_visit_date' => $visit->next_visit_date ?: $riskAssessment['nextVisit']->toDateString(),
                'urgency' => $riskAssessment['urgency'] ?? null,
                'bp_assessment' => $riskAssessment['bp_assessment'] ?? null,
                'bp_verification_status' => $riskAssessment['bp_assessment']['verification_status'] ?? BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED,
            ]);

            Log::info('Auto-recalculated risk assessment for patient ID: ' . $patientId . ', visit ID: ' . $visit->id);
        }
    }
}
