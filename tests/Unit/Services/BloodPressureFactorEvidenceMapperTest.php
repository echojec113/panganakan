<?php

use App\Services\BloodPressureFactorEvidenceMapper;

uses(Tests\TestCase::class);

function bpAssessmentFactory(string $reasonCode, bool $triggered): array
{
    $base = [
        'triggered' => $triggered,
        'reason_code' => $reasonCode,
        'urgency' => $triggered ? 'PROMPT' : null,
        'initial_bp' => $triggered ? ['systolic' => 140, 'diastolic' => 90] : ['systolic' => 120, 'diastolic' => 80],
        'repeat_bp' => null,
        'repeat_interpretation' => 'NORMAL',
        'clinical_interpretation' => 'Interpretation text.',
        'suggested_action' => 'Action text.',
    ];

    if ($triggered) {
        $base['threshold'] = 'Systolic >= 140 mmHg or diastolic >= 90 mmHg';
        $base['label'] = $reasonCode === 'BP-URG'
            ? 'Severe-range blood-pressure finding'
            : 'Elevated blood-pressure finding';
    }

    return $base;
}

test('bp-h triggered maps to BP-H evidence', function () {
    $mapper = new BloodPressureFactorEvidenceMapper;
    $evidence = $mapper->toEvidence(bpAssessmentFactory('BP-H', true));

    expect($evidence)->not->toBeNull();
    expect($evidence->code)->toBe('BP-H');
    expect($evidence->category)->toBe('VITAL_SIGNS');
    expect($evidence->observed_value)->toHaveKey('initial');
    expect($evidence->observed_value['initial'])->toBe('140/90');
});

test('bp-urg triggered maps to BP-URG evidence', function () {
    $mapper = new BloodPressureFactorEvidenceMapper;
    $evidence = $mapper->toEvidence(bpAssessmentFactory('BP-URG', true));

    expect($evidence)->not->toBeNull();
    expect($evidence->code)->toBe('BP-URG');
    expect($evidence->urgency)->toBe('PROMPT');
});

test('non-triggered assessment maps to null even if reason code set', function () {
    $mapper = new BloodPressureFactorEvidenceMapper;
    $evidence = $mapper->toEvidence(bpAssessmentFactory('BP-H', false));

    expect($evidence)->toBeNull();
});

test('unknown reason code maps to null (fail safe)', function () {
    $mapper = new BloodPressureFactorEvidenceMapper;
    $evidence = $mapper->toEvidence(bpAssessmentFactory('MYSTERY', true));

    expect($evidence)->toBeNull();
});

test('repeat reading is represented in observed value', function () {
    $assessment = bpAssessmentFactory('BP-H', true);
    $assessment['repeat_bp'] = ['systolic' => 145, 'diastolic' => 92];
    $assessment['repeat_interpretation'] = 'ELEVATED';

    $mapper = new BloodPressureFactorEvidenceMapper;
    $evidence = $mapper->toEvidence($assessment);

    expect($evidence->observed_value)->toHaveKey('repeat');
    expect($evidence->observed_value['repeat'])->toBe('145/92');
});

test('missing reading returns not recorded text', function () {
    $mapper = new BloodPressureFactorEvidenceMapper;
    $evidence = $mapper->toEvidence(bpAssessmentFactory('BP-H', true));

    expect($evidence->observed_value['initial'])->toBe('140/90');
});