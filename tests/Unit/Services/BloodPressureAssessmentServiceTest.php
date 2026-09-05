<?php

use App\Services\BloodPressureAssessmentService;

uses(Tests\TestCase::class);

test('normal bp returns not triggered with no verification needed', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 120, bpDia: 80);

    expect($result['triggered'])->toBeFalse();
    expect($result['reason_code'])->toBeNull();
    expect($result['risk_level'])->toBeNull();
    expect($result['urgency'])->toBeNull();
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_NOT_REQUIRED);
    expect($result['verification_note'])->toBeNull();
    expect($result['initial_bp'])->toBe(['systolic' => 120, 'diastolic' => 80]);
    expect($result['repeat_bp'])->toBeNull();
    expect($result['threshold'])->toBeNull();
    expect($result['label'])->toBeNull();
    expect($result['clinical_interpretation'])->toBeNull();
    expect($result['suggested_verification'])->toBeNull();
    expect($result['suggested_action'])->toBeNull();
});

test('elevated bp systolic triggers bp-h with prompt urgency', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 140, bpDia: 80);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['risk_level'])->toBe('HIGH');
    expect($result['urgency'])->toBe(BloodPressureAssessmentService::URGENCY_PROMPT);
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
    expect($result['threshold'])->toContain('Systolic >= 140');
    expect($result['label'])->toBe('Elevated blood-pressure finding');
});

test('severe bp systolic triggers bp-urg with urgent clinical review', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 160, bpDia: 95);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-URG');
    expect($result['risk_level'])->toBe('HIGH');
    expect($result['urgency'])->toBe(BloodPressureAssessmentService::URGENCY_URGENT);
    expect($result['threshold'])->toContain('Severe');
    expect($result['label'])->toBe('Severe-range blood-pressure finding');
    expect($result['effective_max_systolic'])->toBe(160);
    expect($result['effective_max_diastolic'])->toBe(95);
});

test('severe bp diastolic triggers bp-urg', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 150, bpDia: 110);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-URG');
    expect($result['urgency'])->toBe(BloodPressureAssessmentService::URGENCY_URGENT);
    expect($result['effective_max_systolic'])->toBe(150);
    expect($result['effective_max_diastolic'])->toBe(110);
});

test('initial bp-h with elevated repeat returns repeat completed', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 140, bpDia: 85, repeatSys: 145, repeatDia: 88);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED);
    expect($result['repeat_bp'])->toBe(['systolic' => 145, 'diastolic' => 88]);
});

test('initial bp-h with normal repeat still returns triggered with repeat completed', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 140, bpDia: 85, repeatSys: 120, repeatDia: 80);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED);
    expect($result['repeat_bp'])->toBe(['systolic' => 120, 'diastolic' => 80]);
});

test('initial elevated with explicit pending repeat status preserved', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(
        bpSys: 140,
        bpDia: 80,
        verificationStatus: BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT
    );

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

test('initial severe with unable to repeat status preserved', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(
        bpSys: 160,
        bpDia: 95,
        verificationStatus: BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT,
        verificationNote: 'Patient declined to wait for repeat measurement'
    );

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-URG');
    expect($result['urgency'])->toBe(BloodPressureAssessmentService::URGENCY_URGENT);
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT);
    expect($result['verification_note'])->toBe('Patient declined to wait for repeat measurement');
});

test('null values do not throw and return not triggered', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: null, bpDia: null);

    expect($result['triggered'])->toBeFalse();
    expect($result['reason_code'])->toBeNull();
    expect($result['initial_bp'])->toBe(['systolic' => 0, 'diastolic' => 0]);
});

test('bp-h with systolic only above threshold returns bp-h', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 145, bpDia: 80);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

test('repeat bp with only systolic provided does not cause error', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 145, bpDia: 80, repeatSys: 140);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
    expect($result['repeat_bp'])->toBeNull();
});

test('bp-h with repeat that normalizes still triggers bp-h', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 140, bpDia: 85, repeatSys: 120, repeatDia: 80);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED);
    expect($result['initial_bp'])->toBe(['systolic' => 140, 'diastolic' => 85]);
    expect($result['repeat_bp'])->toBe(['systolic' => 120, 'diastolic' => 80]);
});

test('determineVerificationStatus returns pending when only one repeat value given', function () {
    $service = new BloodPressureAssessmentService;

    $reflection = new ReflectionMethod($service, 'determineVerificationStatus');
    $reflection->setAccessible(true);

    $status = $reflection->invoke($service, null, 140, null);

    expect($status)->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

test('determineVerificationStatus returns repeat completed when both repeat values given', function () {
    $service = new BloodPressureAssessmentService;

    $reflection = new ReflectionMethod($service, 'determineVerificationStatus');
    $reflection->setAccessible(true);

    $status = $reflection->invoke($service, null, 140, 90);

    expect($status)->toBe(BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED);
});

test('determineVerificationStatus returns unable to repeat when explicit status and note set', function () {
    $service = new BloodPressureAssessmentService;

    $reflection = new ReflectionMethod($service, 'determineVerificationStatus');
    $reflection->setAccessible(true);

    $status = $reflection->invoke(
        $service,
        BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT,
        null,
        null,
        'Patient declined to wait for repeat measurement'
    );

    expect($status)->toBe(BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT);
});

test('determineVerificationStatus ignores unable status without a note', function () {
    $service = new BloodPressureAssessmentService;

    $reflection = new ReflectionMethod($service, 'determineVerificationStatus');
    $reflection->setAccessible(true);

    $status = $reflection->invoke(
        $service,
        BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT,
        null,
        null,
        null
    );

    expect($status)->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

test('severe repeat with normal initial triggers bp-urg', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(bpSys: 120, bpDia: 80, repeatSys: 165, repeatDia: 110);

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-URG');
    expect($result['urgency'])->toBe(BloodPressureAssessmentService::URGENCY_URGENT);
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED);
    expect($result['repeat_interpretation'])->toBe(BloodPressureAssessmentService::REPEAT_SEVERE);
    expect($result['effective_max_systolic'])->toBe(165);
    expect($result['effective_max_diastolic'])->toBe(110);
});

test('forged repeat completed without repeat pair falls back to pending', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(
        bpSys: 140,
        bpDia: 80,
        verificationStatus: BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED
    );

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

test('explicit unable without repeat pair requires a note', function () {
    $service = new BloodPressureAssessmentService;

    $result = $service->assess(
        bpSys: 140,
        bpDia: 80,
        verificationStatus: BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT
    );

    expect($result['triggered'])->toBeTrue();
    expect($result['reason_code'])->toBe('BP-H');
    expect($result['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

test('classify repeat returns not recorded when missing', function () {
    $service = new BloodPressureAssessmentService;

    expect($service->classifyRepeat(null, null))->toBe(BloodPressureAssessmentService::REPEAT_NOT_RECORDED);
});

test('classify repeat returns normal when below thresholds', function () {
    $service = new BloodPressureAssessmentService;

    expect($service->classifyRepeat(120, 80))->toBe(BloodPressureAssessmentService::REPEAT_NORMAL);
});

test('classify repeat returns elevated when systolic only elevated', function () {
    $service = new BloodPressureAssessmentService;

    expect($service->classifyRepeat(140, 80))->toBe(BloodPressureAssessmentService::REPEAT_ELEVATED);
});

test('classify repeat returns severe when diastolic severe', function () {
    $service = new BloodPressureAssessmentService;

    expect($service->classifyRepeat(150, 110))->toBe(BloodPressureAssessmentService::REPEAT_SEVERE);
});
