<?php

namespace App\Services;

use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;

class MedicalHistoryConditionSyncService
{
    /**
     * One-way monotonic synchronization of confirmed prenatal-visit
     * diabetes/anemia values into the patient's active Medical History.
     *
     * - A true visit value may set the background Medical History value to true.
     * - A false visit value never clears a true Medical History value.
     * - A missing Medical History is never created here.
     * - No other checkbox (hypertension, legacy warning fields, etc.) is
     *   synchronized, and no assessment is triggered from inside this service.
     *
     * The dated Prenatal Visit remains the source of truth for the visit
     * assessment; Medical History is pregnancy-level background documentation.
     *
     * @param Patient $patient
     * @param bool $diabetes Confirmed diabetes value from the persisted visit.
     * @param bool $anemia Confirmed anemia value from the persisted visit.
     * @param PrenatalVisit|null $visit Optional persisted visit, included in metadata.
     * @return array{changed: bool, updated_fields: array<int, string>, skipped_reason: ?string, visit_id: ?int}
     */
    public function syncConfirmedVisitConditions(
        Patient $patient,
        bool $diabetes,
        bool $anemia,
        ?PrenatalVisit $visit = null
    ): array {
        // A completed pregnancy is never modified by visit synchronization.
        // The visit's own assessment is already persisted; the Medical History
        // background record of a delivered patient stays frozen.
        if ($patient->isDelivered()) {
            return [
                'changed' => false,
                'updated_fields' => [],
                'skipped_reason' => 'PATIENT_DELIVERED',
                'visit_id' => $visit?->id,
            ];
        }

        $history = MedicalHistory::where('patient_id', $patient->id)
            ->orderBy('id')
            ->first();

        if (!$history) {
            return [
                'changed' => false,
                'updated_fields' => [],
                'skipped_reason' => 'NO_ACTIVE_MEDICAL_HISTORY',
                'visit_id' => $visit?->id,
            ];
        }

        $updates = [];

        if ($diabetes && !(bool) $history->diabetes) {
            $updates['diabetes'] = true;
        }

        if ($anemia && !(bool) $history->anemia) {
            $updates['anemia'] = true;
        }

        if (empty($updates)) {
            return [
                'changed' => false,
                'updated_fields' => [],
                'skipped_reason' => null,
                'visit_id' => $visit?->id,
            ];
        }

        $history->update($updates);

        return [
            'changed' => true,
            'updated_fields' => array_keys($updates),
            'skipped_reason' => null,
            'visit_id' => $visit?->id,
        ];
    }
}
