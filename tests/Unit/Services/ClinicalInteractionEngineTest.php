<?php

use App\Services\ClinicalInteractionEngine;
use App\ValueObjects\AssessmentContext;
use App\ValueObjects\ClinicalFactorEvidence;

uses(Tests\TestCase::class);

test('engine returns no evidence in sprint 13 because no interaction is active', function () {
    $context = new AssessmentContext(
        patient_id: 1,
        ultrasound_date: '2026-08-01',
        gestational_age: 32,
        patient_status: 'ONGOING',
    );

    $triggered = [
        ClinicalFactorEvidence::forCode('US-P01', 'Breech'),
        ClinicalFactorEvidence::forCode('BP-H', 150),
    ];

    $evidence = (new ClinicalInteractionEngine)->evaluate($context, $triggered);

    expect($evidence)->toBe([]);
});