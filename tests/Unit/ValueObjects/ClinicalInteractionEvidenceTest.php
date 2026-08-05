<?php

use App\ValueObjects\ClinicalInteractionEvidence;

uses(Tests\TestCase::class);

test('evidence can be built for a registered draft candidate', function () {
    $evidence = new ClinicalInteractionEvidence(
        code: 'INT-US-PRESENTATION-GA',
        label: 'Abnormal fetal presentation with gestational-age context',
        required_factor_codes: ['US-P01'],
        observed_context: ['ultrasound_date' => '2026-08-01'],
        explanation: 'Documentation only.',
    );

    $array = $evidence->toArray();

    expect($array['code'])->toBe('INT-US-PRESENTATION-GA');
    expect($array['observed_context']['ultrasound_date'])->toBe('2026-08-01');
});

test('unknown interaction code throws OutOfBoundsException', function () {
    new ClinicalInteractionEvidence(
        code: 'NOT-REGISTERED',
        label: 'Nope',
        required_factor_codes: [],
        observed_context: [],
    );
})->throws(OutOfBoundsException::class);

test('normalizeList drops unknown keys and skips malformed rows', function () {
    $normalized = ClinicalInteractionEvidence::normalizeList([
        [
            'code' => 'INT-WARNING-BP',
            'label' => 'Warning symptom with elevated or severe blood pressure',
            'required_factor_codes' => ['BP-H', 'BP-URG'],
            'observed_context' => ['patient_name' => 'Jane', 'triggered' => true],
            'extra_key' => 'dropped',
        ],
        ['code' => 'NOT-REGISTERED', 'label' => 'Skip me'],
        'not-an-array',
    ]);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0])->not->toHaveKey('extra_key');
    expect($normalized[0]['observed_context']['triggered'])->toBeTrue();
});

test('normalizeList accepts a single evidence object', function () {
    $evidence = new ClinicalInteractionEvidence(
        code: 'INT-ANEMIA-LAB',
        label: 'Confirmed laboratory anemia severity',
        required_factor_codes: ['AN-01'],
        observed_context: [],
    );

    $normalized = ClinicalInteractionEvidence::normalizeList($evidence);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0]['code'])->toBe('INT-ANEMIA-LAB');
});