<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Ultrasound;

class ClinicalRuleEngine
{
    public function evaluate(
        Patient $patient,
        array $inputs,
        ?Ultrasound $ultrasound
    ): array {
        $reasons = [];

        if ($patient->age < 19) {
            $reasons[] = "Teenage pregnancy (under 19)";
        } elseif ($patient->age >= 35 && $patient->gravida == 1 && $patient->para == 0) {
            $reasons[] = "Advanced maternal age (35+) and first pregnancy";
        }

        if ($inputs['diabetes'] == 1) {
            $reasons[] = "Diabetes";
        }

        if ($inputs['anemia'] == 1) {
            $reasons[] = "Anemia";
        }

        if ($patient->previous_cs == 1) {
            $reasons[] = "Previous cesarean section";
        }

        if ($patient->miscarriage >= 3) {
            $reasons[] = "History of " . $patient->miscarriage . " miscarriage(s)";
        }

        if ($ultrasound) {
            $presentation = strtoupper(trim((string) $ultrasound->presentation));
            $amnioticFluid = strtoupper(trim((string) $ultrasound->amniotic_fluid));
            $fetalHeartbeat = strtoupper(trim((string) $ultrasound->fetal_heartbeat));

            if (in_array($presentation, ['BREECH', 'TRANSVERSE', 'OBLIQUE'], true)) {
                $reasons[] = "Abnormal fetal presentation ({$presentation})";
            }

            if (in_array($amnioticFluid, ['LOW', 'HIGH'], true)) {
                $reasons[] = "Amniotic fluid abnormality ({$amnioticFluid})";
            }

            if (in_array($fetalHeartbeat, ['WEAK', 'ABNORMAL', 'ABSENT'], true)) {
                $reasons[] = "Fetal heartbeat abnormality ({$fetalHeartbeat})";
            }
        }

        return array_unique($reasons);
    }
}
