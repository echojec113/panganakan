<?php

namespace App\Services;

class BloodPressureAssessmentService
{
    public const BP_H_SYSTOLIC = 140;
    public const BP_H_DIASTOLIC = 90;
    public const BP_URG_SYSTOLIC = 160;
    public const BP_URG_DIASTOLIC = 110;

    public const VERIFICATION_NOT_REQUIRED = 'NOT_REQUIRED';
    public const VERIFICATION_PENDING_REPEAT = 'PENDING_REPEAT';
    public const VERIFICATION_REPEAT_COMPLETED = 'REPEAT_COMPLETED';
    public const VERIFICATION_UNABLE_TO_REPEAT = 'UNABLE_TO_REPEAT';

    public const URGENCY_PROMPT = 'PROMPT';
    public const URGENCY_URGENT = 'URGENT_CLINICAL_REVIEW';

    public function assess(
        ?int $bpSys,
        ?int $bpDia,
        ?int $repeatSys = null,
        ?int $repeatDia = null,
        ?string $verificationStatus = null,
        ?string $verificationNote = null
    ): array {
        $bpSys = (int) ($bpSys ?? 0);
        $bpDia = (int) ($bpDia ?? 0);

        $isInitialElevated = $this->isInitialElevated($bpSys, $bpDia);
        $isInitialSevere = $this->isInitialSevere($bpSys, $bpDia);
        $isRepeatSevere = $this->isRepeatSevere($repeatSys, $repeatDia);

        if (!$isInitialElevated) {
            return [
                'triggered' => false,
                'reason_code' => null,
                'risk_level' => null,
                'urgency' => null,
                'initial_bp' => ['systolic' => $bpSys, 'diastolic' => $bpDia],
                'repeat_bp' => $this->buildRepeatBp($repeatSys, $repeatDia),
                'verification_status' => self::VERIFICATION_NOT_REQUIRED,
                'verification_note' => null,
                'threshold' => null,
                'label' => null,
                'clinical_interpretation' => null,
                'suggested_verification' => null,
                'suggested_action' => null,
            ];
        }

        $verificationStatus = $this->determineVerificationStatus(
            $verificationStatus,
            $repeatSys,
            $repeatDia,
            $verificationNote
        );

        if ($isInitialSevere || $isRepeatSevere) {
            $repeatDia = $repeatDia ?? 0;
            $repeatSys = $repeatSys ?? 0;
            $effectiveSys = max($bpSys, $repeatSys);
            $effectiveDia = max($bpDia, $repeatDia);

            return [
                'triggered' => true,
                'reason_code' => 'BP-URG',
                'risk_level' => 'HIGH',
                'urgency' => self::URGENCY_URGENT,
                'initial_bp' => ['systolic' => $bpSys, 'diastolic' => $bpDia],
                'repeat_bp' => $this->buildRepeatBp($repeatSys, $repeatDia),
                'verification_status' => $verificationStatus,
                'verification_note' => $verificationNote,
                'threshold' => "Severe: systolic >= " . self::BP_URG_SYSTOLIC
                    . " or diastolic >= " . self::BP_URG_DIASTOLIC,
                'label' => 'Severe-range blood-pressure finding',
                'clinical_interpretation' => "The recorded blood pressure reading is in the severe range and requires immediate repeat measurement and urgent qualified clinical review.",
                'suggested_verification' => "Repeat or confirm the measurement according to the clinic's approved protocol. Do not delay qualified clinical review or referral evaluation while awaiting repeat measurement.",
                'suggested_action' => "Immediate qualified assessment and referral evaluation are recommended. Ensure transport, receiving facility, and handover according to clinic protocol.",
                'effective_max_systolic' => $effectiveSys,
                'effective_max_diastolic' => $effectiveDia,
            ];
        }

        return [
            'triggered' => true,
            'reason_code' => 'BP-H',
            'risk_level' => 'HIGH',
            'urgency' => self::URGENCY_PROMPT,
            'initial_bp' => ['systolic' => $bpSys, 'diastolic' => $bpDia],
            'repeat_bp' => $this->buildRepeatBp($repeatSys, $repeatDia),
            'verification_status' => $verificationStatus,
            'verification_note' => $verificationNote,
            'threshold' => "Systolic >= " . self::BP_H_SYSTOLIC
                . " or diastolic >= " . self::BP_H_DIASTOLIC,
            'label' => 'Elevated blood-pressure finding',
            'clinical_interpretation' => "The recorded reading met the system's elevated blood-pressure screening threshold.",
            'suggested_verification' => "Repeat or confirm the measurement according to clinic protocol.",
            'suggested_action' => "Qualified clinic personnel should review the initial and repeat findings and evaluate referral needs according to clinic policy.",
        ];
    }

    public function isInitialElevated(int $bpSys, int $bpDia): bool
    {
        return $bpSys >= self::BP_H_SYSTOLIC || $bpDia >= self::BP_H_DIASTOLIC;
    }

    public function isInitialSevere(int $bpSys, int $bpDia): bool
    {
        return $bpSys >= self::BP_URG_SYSTOLIC || $bpDia >= self::BP_URG_DIASTOLIC;
    }

    public function isRepeatSevere(?int $repeatSys, ?int $repeatDia): bool
    {
        if ($repeatSys === null || $repeatDia === null) {
            return false;
        }
        return $repeatSys >= self::BP_URG_SYSTOLIC || $repeatDia >= self::BP_URG_DIASTOLIC;
    }

    public function determineVerificationStatus(
        ?string $explicitStatus,
        ?int $repeatSys,
        ?int $repeatDia,
        ?string $verificationNote = null
    ): string {
        if ($explicitStatus === self::VERIFICATION_UNABLE_TO_REPEAT) {
            return self::VERIFICATION_UNABLE_TO_REPEAT;
        }

        if ($repeatSys !== null && $repeatDia !== null) {
            return self::VERIFICATION_REPEAT_COMPLETED;
        }

        return self::VERIFICATION_PENDING_REPEAT;
    }

    private function buildRepeatBp(?int $repeatSys, ?int $repeatDia): ?array
    {
        if ($repeatSys === null || $repeatDia === null) {
            return null;
        }
        return ['systolic' => $repeatSys, 'diastolic' => $repeatDia];
    }
}
