<?php

use App\Services\DecisionIntegrationService;

uses(Tests\TestCase::class);

test('missing records returns assessment incomplete', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide(['Medical History'], [], null);

    expect($result['risk_level'])->toBe('ASSESSMENT INCOMPLETE');
    expect($result['decision_source'])->toBe('COMPLETENESS');
});

test('missing record labels appear in original order', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide(
        ['Medical History', 'Ultrasound Record', 'Birth Plan'],
        [],
        null
    );

    expect($result['assessment'])->toContain('Medical History');
    expect($result['assessment'])->toContain('Ultrasound Record');
    expect($result['assessment'])->toContain('Birth Plan');
    expect($result['missing_records'])->toBe(['Medical History', 'Ultrasound Record', 'Birth Plan']);
});

test('rule reasons return high', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], ['Teenage pregnancy (under 19)'], null);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
});

test('rule high preserves first three reasons summary', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], ['A', 'B', 'C'], null);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['assessment'])->toContain('A');
    expect($result['assessment'])->toContain('B');
    expect($result['assessment'])->toContain('C');
    expect($result['reasons'])->toBe(['A', 'B', 'C']);
});

test('more than three reasons adds and X more factors wording', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], ['A', 'B', 'C', 'D', 'E'], null);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['assessment'])->toContain('and 2 more factor(s).');
    expect($result['reasons'])->toHaveCount(5);
});

test('rule high overrides ml low', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide(
        [],
        ['Severe hypertension'],
        ['valid' => true, 'prediction' => 'LOW']
    );

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
});

test('valid ml high returns high', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], [], [
        'valid' => true, 'prediction' => 'HIGH',
    ]);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('MACHINE_LEARNING');
    expect($result['ml_prediction'])->toBe('HIGH');
    expect($result['ml_valid'])->toBeTrue();
});

test('valid ml low returns low', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], [], [
        'valid' => true, 'prediction' => 'LOW',
    ]);

    expect($result['risk_level'])->toBe('LOW');
    expect($result['decision_source'])->toBe('MACHINE_LEARNING');
    expect($result['ml_prediction'])->toBe('LOW');
    expect($result['ml_valid'])->toBeTrue();
});

test('invalid ml returns assessment incomplete', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], [], [
        'valid' => false, 'prediction' => null,
    ]);

    expect($result['risk_level'])->toBe('ASSESSMENT INCOMPLETE');
    expect($result['decision_source'])->toBe('MACHINE_LEARNING_INVALID');
});

test('empty ml result returns assessment incomplete', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], [], []);

    expect($result['risk_level'])->toBe('ASSESSMENT INCOMPLETE');
});

test('decision source is correct for every path', function () {
    $service = new DecisionIntegrationService;

    $completeness = $service->decide(['Missing'], [], null);
    expect($completeness['decision_source'])->toBe('COMPLETENESS');

    $rule = $service->decide([], ['Risk'], null);
    expect($rule['decision_source'])->toBe('RULE_BASED');

    $mlHigh = $service->decide([], [], ['valid' => true, 'prediction' => 'HIGH']);
    expect($mlHigh['decision_source'])->toBe('MACHINE_LEARNING');

    $mlLow = $service->decide([], [], ['valid' => true, 'prediction' => 'LOW']);
    expect($mlLow['decision_source'])->toBe('MACHINE_LEARNING');

    $invalid = $service->decide([], [], ['valid' => false, 'prediction' => null]);
    expect($invalid['decision_source'])->toBe('MACHINE_LEARNING_INVALID');
});

test('metadata keys are present in every returned result', function () {
    $service = new DecisionIntegrationService;

    $scenarios = [
        $service->decide(['Medical History'], [], null),
        $service->decide([], ['Diabetes'], null),
        $service->decide([], [], ['valid' => true, 'prediction' => 'HIGH']),
        $service->decide([], [], ['valid' => true, 'prediction' => 'LOW']),
        $service->decide([], [], ['valid' => false, 'prediction' => null]),
    ];

    foreach ($scenarios as $result) {
        expect($result)->toHaveKey('decision_source');
        expect($result)->toHaveKey('missing_records');
        expect($result)->toHaveKey('rule_reasons');
        expect($result)->toHaveKey('ml_prediction');
        expect($result)->toHaveKey('ml_valid');
    }
});

test('existing five public keys remain present', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], [], ['valid' => true, 'prediction' => 'LOW']);

    expect($result)->toHaveKey('risk_level');
    expect($result)->toHaveKey('assessment');
    expect($result)->toHaveKey('recommendation');
    expect($result)->toHaveKey('reasons');
    expect($result)->toHaveKey('nextVisit');
});

test('rule-based reasons are preserved', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], ['Teenage pregnancy (under 19)', 'Diabetes'], null);

    expect($result['reasons'])->toBe(['Teenage pregnancy (under 19)', 'Diabetes']);
    expect($result['rule_reasons'])->toBe(['Teenage pregnancy (under 19)', 'Diabetes']);
});

test('ml raw output is not exposed', function () {
    $service = new DecisionIntegrationService;

    $result = $service->decide([], [], ['valid' => true, 'prediction' => 'LOW']);

    expect($result)->not->toHaveKey('raw_output');
    expect($result)->not->toHaveKey('parsed_output');
});
