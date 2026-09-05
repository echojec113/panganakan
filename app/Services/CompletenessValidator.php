<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\MedicalHistory;
use App\Models\Ultrasound;
use App\Models\BirthPlan;

class CompletenessValidator
{
    public function missingRequiredRecords(Patient $patient): array
    {
        $missing = [];

        if (!MedicalHistory::where('patient_id', $patient->id)->exists()) {
            $missing[] = 'Medical History';
        }

        if (!Ultrasound::where('patient_id', $patient->id)->exists()) {
            $missing[] = 'Ultrasound Record';
        }

        if (!BirthPlan::where('patient_id', $patient->id)->exists()) {
            $missing[] = 'Birth Plan';
        }

        return $missing;
    }

    public function isComplete(Patient $patient): bool
    {
        return empty($this->missingRequiredRecords($patient));
    }
}
