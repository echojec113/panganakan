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

    public const REPEAT_NOT_RECORDED = 'NOT_RECORDED';
    public const REPEAT_NORMAL = 'NORMAL';
    public const REPEAT_ELEVATED = 'ELEVATED';
    public const REPEAT_SEVERE = 'SEVERE';

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

        $isInitialSevere = $this->isInitialSevere($bpSys, $bpDia);
        $isRepeatSevere = $this->isRepeatSevere($repeatSys, $repeatDia);

        // Severe-range findings are evaluated first, whether they come from the
        // initial reading or the repeat pair.
        if ($isInitialSevere || $isRepeatSevere) {
            $computedStatus = $this->determineVerificationStatus(
                $verificationStatus,
                $repeatSys,
                $repeatDia,
                $verificationNote
            );

            return [
                'triggered' => true,
                'reason_code' => 'BP-URG',
                'risk_level' => 'HIGH',
                'urgency' => self::URGENCY_URGENT,
                'initial_bp' => ['systolic' => $bpSys, 'diastolic' => $bpDia],
                'repeat_bp' => $this->buildRepeatBp($repeatSys, $repeatDia),
                'repeat_interpretation' => $this->classifyRepeat($repeatSys, $repeatDia),
                'verification_status' => $computedStatus,
                'verification_note' => $this->acceptedVerificationNote($computedStatus, $verificationNote),
                'threshold' => "Severe: systolic >= " . self::BP_URG_SYSTOLIC
                    . " or diastolic >= " . self::BP_URG_DIASTOLIC,
                'label' => 'Severe-range blood-pressure finding',
                'clinical_interpretation' => "The recorded reading met the severe-range screening threshold and requires urgent qualified clinical review.",
                'suggested_verification' => "Repeat or confirm the measurement according to the clinic's approved protocol. Do not delay qualified clinical review or referral evaluation while awaiting repeat measurement.",
                'suggested_action' => "Immediate qualified assessment and referral evaluation are recommended according to clinic protocol.",
                'effective_max_systolic' => max($bpSys, (int) ($repeatSys ?? 0)),
                'effective_max_diastolic' => max($bpDia, (int) ($repeatDia ?? 0)),
            ];
        }

        $isInitialElevated = $this->isInitialElevated($bpSys, $bpDia);

        if (!$isInitialElevated) {
            return [
                'triggered' => false,
                'reason_code' => null,
                'risk_level' => null,
                'urgency' => null,
                'initial_bp' => ['systolic' => $bpSys, 'diastolic' => $bpDia],
                'repeat_bp' => $this->buildRepeatBp($repeatSys, $repeatDia),
                'repeat_interpretation' => $this->classifyRepeat($repeatSys, $repeatDia),
                'verification_status' => self::VERIFICATION_NOT_REQUIRED,
                'verification_note' => null,
                'threshold' => null,
                'label' => null,
                'clinical_interpretation' => null,
                'suggested_verification' => null,
                'suggested_action' => null,
            ];
        }

        $computedStatus = $this->determineVerificationStatus(
            $verificationStatus,
            $repeatSys,
            $repeatDia,
            $verificationNote
        );

        return [
            'triggered' => true,
            'reason_code' => 'BP-H',
            'risk_level' => 'HIGH',
            'urgency' => self::URGENCY_PROMPT,
            'initial_bp' => ['systolic' => $bpSys, 'diastolic' => $bpDia],
            'repeat_bp' => $this->buildRepeatBp($repeatSys, $repeatDia),
            'repeat_interpretation' => $this->classifyRepeat($repeatSys, $repeatDia),
            'verification_status' => $computedStatus,
            'verification_note' => $this->acceptedVerificationNote($computedStatus, $verificationNote),
            'threshold' => "Systolic >= " . self::BP_H_SYSTOLIC
                . " or diastolic >= " . self::BP_H_DIASTOLIC,
            'label' => 'Elevated blood-pressure finding',
            'clinical_interpretation' => "The recorded reading met the system's elevated blood-pressure screening threshold.",
            'suggested_verification' => "Repeat or confirm the measurement according to the clinic's approved protocol.",
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
        if ($repeatSys !== null && $repeatDia !== null) {
            return self::VERIFICATION_REPEAT_COMPLETED;
        }

        if ($explicitStatus === self::VERIFICATION_UNABLE_TO_REPEAT
            && $this->hasValidVerificationNote($verificationNote)) {
            return self::VERIFICATION_UNABLE_TO_REPEAT;
        }

        return self::VERIFICATION_PENDING_REPEAT;
    }

    public function classifyRepeat(?int $repeatSys, ?int $repeatDia): string
    {
        if ($repeatSys === null || $repeatDia === null) {
            return self::REPEAT_NOT_RECORDED;
        }
        if ($repeatSys >= self::BP_URG_SYSTOLIC || $repeatDia >= self::BP_URG_DIASTOLIC) {
            return self::REPEAT_SEVERE;
        }
        if ($repeatSys >= self::BP_H_SYSTOLIC || $repeatDia >= self::BP_H_DIASTOLIC) {
            return self::REPEAT_ELEVATED;
        }
        return self::REPEAT_NORMAL;
    }

    private function hasValidVerificationNote(?string $note): bool
    {
        return $note !== null && trim($note) !== '';
    }

    private function acceptedVerificationNote(string $computedStatus, ?string $note): ?string
    {
        if ($computedStatus !== self::VERIFICATION_UNABLE_TO_REPEAT) {
            return null;
        }

        return $this->hasValidVerificationNote($note) ? trim($note) : null;
    }

    private function buildRepeatBp(?int $repeatSys, ?int $repeatDia): ?array
    {
        if ($repeatSys === null || $repeatDia === null) {
            return null;
        }
        return ['systolic' => $repeatSys, 'diastolic' => $repeatDia];
    }
}
