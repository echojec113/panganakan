<?php

namespace App\Services;

use App\Models\Patient;
use App\Support\ClinicalFactorRegistry;
use App\ValueObjects\ClinicalFactorEvidence;
use App\ValueObjects\UltrasoundSnapshot;

class ClinicalRuleEngine
{
    /**
     * Evaluate deterministic clinical rules and return the legacy reason label
     * strings in rule order, with duplicates removed.
     *
     * This method is a thin compatibility wrapper over evaluateDetailed().
     * The rule conditions themselves live in exactly one place.
     *
     * @return array<int, string>
     */
    public function evaluate(
        Patient $patient,
        array $inputs,
        ?UltrasoundSnapshot $ultrasound
    ): array {
        $evidence = $this->evaluateDetailed($patient, $inputs, $ultrasound);

        return array_values(array_unique(array_map(
            static fn (ClinicalFactorEvidence $factor) => $factor->label,
            $evidence
        )));
    }

    /**
     * Evaluate deterministic clinical rules and return structured factor
     * evidence in rule order.
     *
     * The ultrasound is consumed as a controlled snapshot (never an Eloquent
     * model), so the engine can only ever evaluate the exact values that were
     * captured in the assessment context.
     *
     * @return array<int, ClinicalFactorEvidence>
     */
    public function evaluateDetailed(
        Patient $patient,
        array $inputs,
        ?UltrasoundSnapshot $ultrasound
    ): array {
        $evidence = [];

        if ($patient->age < 19) {
            $evidence[] = ClinicalFactorEvidence::forCode('AGE-Y', $patient->age);
        } elseif ($patient->age >= 35 && $patient->gravida == 1 && $patient->para == 0) {
            $evidence[] = ClinicalFactorEvidence::forCode('AGE-A', [
                'age' => $patient->age,
                'gravida' => $patient->gravida,
                'para' => $patient->para,
            ]);
        }

        if ($inputs['diabetes'] == 1) {
            $evidence[] = ClinicalFactorEvidence::forCode('DM-01', 'Yes');
        }

        if ($inputs['anemia'] == 1) {
            $evidence[] = ClinicalFactorEvidence::forCode('AN-01', 'Yes');
        }

        if ($patient->previous_cs == 1) {
            $evidence[] = ClinicalFactorEvidence::forCode('CS-01', 'Yes');
        }

        if ($patient->miscarriage >= 3) {
            $evidence[] = ClinicalFactorEvidence::forCode(
                'RM-03',
                $patient->miscarriage,
                label: "History of " . $patient->miscarriage . " miscarriage(s)"
            );
        }

        if ($ultrasound !== null) {
            $presentation = strtoupper(trim($ultrasound->presentation));
            $amnioticFluid = strtoupper(trim($ultrasound->amniotic_fluid));
            $fetalHeartbeat = strtoupper(trim($ultrasound->fetal_heartbeat));

            if (in_array($presentation, ['BREECH', 'TRANSVERSE', 'OBLIQUE'], true)) {
                $evidence[] = ClinicalFactorEvidence::forCode(
                    'US-P01',
                    $presentation,
                    label: "Abnormal fetal presentation ({$presentation})"
                );
            }

            if (in_array($amnioticFluid, ['LOW', 'HIGH'], true)) {
                $evidence[] = ClinicalFactorEvidence::forCode(
                    'US-AF01',
                    $amnioticFluid,
                    label: "Amniotic fluid abnormality ({$amnioticFluid})"
                );
            }

            if (in_array($fetalHeartbeat, ['WEAK', 'ABNORMAL', 'ABSENT'], true)) {
                $evidence[] = ClinicalFactorEvidence::forCode(
                    'US-FH01',
                    $fetalHeartbeat,
                    label: "Fetal heartbeat abnormality ({$fetalHeartbeat})"
                );
            }
        }

        // Governance gate: only factors registered as ACTIVE may be surfaced.
        // This filter never changes a clinical condition; it only guarantees
        // that inactive/deferred codes cannot reach the assessment result.
        return array_values(array_filter(
            $evidence,
            static fn (ClinicalFactorEvidence $factor) => ClinicalFactorRegistry::isActive($factor->code)
        ));
    }
}
