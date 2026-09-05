<?php

use App\ValueObjects\DecisionTraceStep;

uses(Tests\TestCase::class);

test('trace step keeps strings only for code lists', function () {
    $step = new DecisionTraceStep(
        step_code: DecisionTraceStep::STEP_STANDALONE_RULE,
        status: DecisionTraceStep::STATUS_TRIGGERED,
        summary: 'Factors triggered.',
        related_factor_codes: ['BP-H', 'DM-01', 42, null],
        related_interaction_codes: ['CLIN-X', 7, null],
        missing_records: ['Medical History', 0],
    );

    expect($step->step_code)->toBe(DecisionTraceStep::STEP_STANDALONE_RULE);
    expect($step->related_factor_codes)->toBe(['BP-H', 'DM-01']);
    expect($step->related_interaction_codes)->toBe(['CLIN-X']);
    expect($step->missing_records)->toBe(['Medical History']);
});

test('toArray exposes the approved keys', function () {
    $step = new DecisionTraceStep(
        step_code: DecisionTraceStep::STEP_URGENT_BP_CHECK,
        status: DecisionTraceStep::STATUS_TRIGGERED,
        summary: 'Severe-range blood pressure triggered urgent review.',
        related_factor_codes: ['BP-URG'],
        assessed_at: '2026-08-05T10:00:00+00:00',
    );

    $array = $step->toArray();

    expect($array)->toHaveKeys([
        'step_code', 'status', 'summary',
        'related_factor_codes', 'related_interaction_codes',
        'missing_records', 'assessed_at',
    ]);
    expect($array['step_code'])->toBe(DecisionTraceStep::STEP_URGENT_BP_CHECK);
    expect($array['status'])->toBe(DecisionTraceStep::STATUS_TRIGGERED);
    expect($array['assessed_at'])->toBe('2026-08-05T10:00:00+00:00');
});

test('normalizeList drops malformed rows, unknown keys, and invalid codes', function () {
    $normalized = DecisionTraceStep::normalizeList([
        [
            'step_code' => DecisionTraceStep::STEP_CONTEXT_BUILT,
            'status' => DecisionTraceStep::STATUS_COMPLETED,
            'summary' => 'Context built.',
            'related_factor_codes' => ['DM-01'],
            'extra' => 'dropped',
        ],
        ['step_code' => 'NOT-A-STEP', 'status' => 'COMPLETED', 'summary' => 'x'],
        ['step_code' => 'CONTEXT_BUILT', 'status' => 'NOT-A-STATUS', 'summary' => 'x'],
        ['step_code' => 'CONTEXT_BUILT'],
        'not-an-array',
    ]);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0])->not->toHaveKey('extra');
    expect($normalized[0]['related_factor_codes'])->toBe(['DM-01']);
});

test('unknown step code throws', function () {
    new DecisionTraceStep('BOGUS', 'COMPLETED', 'x');
})->throws(InvalidArgumentException::class);

test('unknown status throws', function () {
    new DecisionTraceStep('CONTEXT_BUILT', 'BOGUS', 'x');
})->throws(InvalidArgumentException::class);

test('normalizeList accepts a single step object', function () {
    $step = new DecisionTraceStep(
        DecisionTraceStep::STEP_CONTEXT_BUILT,
        DecisionTraceStep::STATUS_COMPLETED,
        'Context built.',
    );

    $normalized = DecisionTraceStep::normalizeList($step);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0]['step_code'])->toBe(DecisionTraceStep::STEP_CONTEXT_BUILT);
});

test('the seven approved step codes are exactly the pipeline order', function () {
    expect(DecisionTraceStep::STEP_CODES)->toBe([
        'CONTEXT_BUILT',
        'URGENT_BP_CHECK',
        'COMPLETENESS_CHECK',
        'STANDALONE_RULE_EVALUATION',
        'INTERACTION_RULE_EVALUATION',
        'ML_EVALUATION',
        'FINAL_DECISION',
    ]);

    expect(DecisionTraceStep::STATUSES)->toBe(['COMPLETED', 'TRIGGERED', 'SKIPPED', 'BLOCKED']);
});
