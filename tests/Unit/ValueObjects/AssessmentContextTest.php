<?php

use App\ValueObjects\AssessmentContext;

uses(Tests\TestCase::class);

test('allowed keys round-trip through all() and toArray()', function () {
    $context = new AssessmentContext(
        patient_id: 7,
        patient_status: 'ONGOING',
        assessment_date: '2026-08-05',
        ultrasound_id: 12,
        ultrasound_date: '2026-08-01',
        gestational_age: 20,
        lmp: '2026-03-15',
        edd: '2026-12-20',
    );

    $array = $context->toArray();

    expect($array['patient_id'])->toBe(7);
    expect($array['patient_status'])->toBe('ONGOING');
    expect($array['assessment_date'])->toBe('2026-08-05');
    expect($array['ultrasound_id'])->toBe(12);
    expect($array['ultrasound_date'])->toBe('2026-08-01');
    expect($array['gestational_age'])->toBe(20);
});

test('context never carries models objects or unsanitized values', function () {
    $context = new AssessmentContext(
        patient_id: 1,
        visit_inputs: ['bp_sys' => 120, 'free_text' => 'should be dropped'],
        source_summary: ['note' => (object) ['unexpected' => true]],
    );

    expect($context->visit_inputs)->not->toHaveKey('free_text');
    expect($context->visit_inputs['bp_sys'])->toBe(120);
    expect($context->source_summary['note'])->toBe('Recorded');
});

test('normalize drops unknown keys and unknown visit inputs', function () {
    $normalized = AssessmentContext::normalize([
        'patient_id' => 3,
        'not_a_key' => 'drop me',
        'visit_inputs' => ['bp_dia' => 80, 'something_random' => 'x'],
    ]);

    expect($normalized)->not->toHaveKey('not_a_key');
    expect($normalized['visit_inputs'])->toHaveKey('bp_dia');
    expect($normalized['visit_inputs'])->not->toHaveKey('something_random');
});

test('fromArray requires a patient id', function () {
    AssessmentContext::fromArray(['assessment_date' => '2026-08-05']);
})->throws(OutOfBoundsException::class);

test('fromArray rehydrates a valid normalized array', function () {
    $context = AssessmentContext::fromArray([
        'patient_id' => 5,
        'assessment_date' => '2026-08-05',
        'ultrasound_id' => 9,
    ]);

    expect($context->patient_id)->toBe(5);
    expect($context->assessment_date)->toBe('2026-08-05');
    expect($context->ultrasound_id)->toBe(9);
});

test('objects in stored nested arrays are replaced with a neutral value', function () {
    $context = new AssessmentContext(
        patient_id: 1,
        source_summary: ['visit' => (object) ['x' => 1]],
        visit_inputs: ['weight' => 50],
    );

    expect($context->source_summary['visit'])->toBe('Recorded');
    expect($context->visit_inputs['weight'])->toBe(50);
});

test('ultrasound inputs keep only the three approved findings and never a model', function () {
    $context = new AssessmentContext(
        patient_id: 1,
        ultrasound_inputs: [
            'presentation' => 'Cephalic',
            'amniotic_fluid' => 'Normal',
            'fetal_heartbeat' => 'Normal',
            'leaked_key' => 'drop me',
        ],
    );

    expect($context->ultrasound_inputs)->toBe([
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
});

test('normalize preserves approved ultrasound inputs and drops the rest', function () {
    $normalized = AssessmentContext::normalize([
        'patient_id' => 3,
        'ultrasound_inputs' => [
            'presentation' => 'Breech',
            'amniotic_fluid' => 'Low',
            'fetal_heartbeat' => 'Absent',
            'other' => 'x',
        ],
    ]);

    expect($normalized['ultrasound_inputs'])->toBe([
        'presentation' => 'Breech',
        'amniotic_fluid' => 'Low',
        'fetal_heartbeat' => 'Absent',
    ]);
});