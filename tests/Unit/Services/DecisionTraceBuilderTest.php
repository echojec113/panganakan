<?php

use App\Services\DecisionTraceBuilder;
use App\ValueObjects\AssessmentResult;
use App\ValueObjects\ClinicalFactorEvidence;
use App\ValueObjects\DecisionTraceStep;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

function traceResult(array $overrides = []): AssessmentResult
{
    return new AssessmentResult(
        risk_level: $overrides['risk_level'] ?? 'HIGH',
        assessment: 'High-risk pregnancy.',
        recommendation: 'Review recommended.',
        reasons: $overrides['reasons'] ?? ['Diabetes'],
        nextVisit: CarbonImmutable::now()->addDays(3),
        decision_source: $overrides['decision_source'] ?? 'RULE_BASED',
        missing_records: $overrides['missing_records'] ?? [],
        rule_reasons: $overrides['rule_reasons'] ?? ['Diabetes'],
        ml_prediction: $overrides['ml_prediction'] ?? null,
        ml_valid: $overrides['ml_valid'] ?? false,
        urgency: $overrides['urgency'] ?? null,
        bp_assessment: $overrides['bp_assessment'] ?? null,
        factor_evidence: $overrides['factor_evidence'] ?? [
            ClinicalFactorEvidence::forCode('DM-01', true),
        ],
        interaction_evidence: $overrides['interaction_evidence'] ?? [],
        assessed_at: $overrides['assessed_at'] ?? '2026-08-05T10:00:00+00:00',
    );
}

function traceSteps(array $overrides = []): array
{
    return (new DecisionTraceBuilder)->build(traceResult($overrides));
}

function traceSummaryByCode(array $steps, string $code): string
{
    foreach ($steps as $step) {
        if ($step->step_code === $code) {
            return $step->summary;
        }
    }
    return '';
}

test('pipeline order is always the seven approved steps', function () {
    $steps = traceSteps();

    expect(array_map(static fn (DecisionTraceStep $s) => $s->step_code, $steps))->toBe([
        'CONTEXT_BUILT',
        'URGENT_BP_CHECK',
        'COMPLETENESS_CHECK',
        'STANDALONE_RULE_EVALUATION',
        'INTERACTION_RULE_EVALUATION',
        'ML_EVALUATION',
        'FINAL_DECISION',
    ]);
});

test('A: bp-urg with complete records traces the urgent path', function () {
    $steps = traceSteps([
        'decision_source' => 'RULE_BASED',
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'bp_assessment' => ['reason_code' => 'BP-URG', 'label' => 'Severe range'],
        'factor_evidence' => [ClinicalFactorEvidence::forCode('BP-URG', [165, 110])],
    ]);

    $statuses = array_map(static fn (DecisionTraceStep $s) => $s->status, $steps);

    expect($statuses)->toBe([
        'COMPLETED', 'TRIGGERED', 'COMPLETED', 'SKIPPED', 'SKIPPED', 'SKIPPED', 'TRIGGERED',
    ]);
    expect($steps[1]->related_factor_codes)->toContain('BP-URG');
});

test('B: bp-urg with missing records preserves them without blocking the urgent result', function () {
    $steps = traceSteps([
        'decision_source' => 'RULE_BASED',
        'risk_level' => 'HIGH',
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'bp_assessment' => ['reason_code' => 'BP-URG', 'label' => 'Severe range'],
        'missing_records' => ['Medical History'],
        'factor_evidence' => [ClinicalFactorEvidence::forCode('BP-URG', [165, 110])],
    ]);

    $completeness = $steps[2];
    expect($completeness->status)->toBe('COMPLETED');
    expect($completeness->missing_records)->toBe(['Medical History']);
    expect($completeness->summary)->toContain('did not block');
});

test('FIX 1: bp-urg trace never claims the assessment stopped because of completeness', function () {
    $steps = traceSteps([
        'decision_source' => 'RULE_BASED',
        'risk_level' => 'HIGH',
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'bp_assessment' => ['reason_code' => 'BP-URG', 'label' => 'Severe range'],
        'missing_records' => ['Medical History', 'Ultrasound Record'],
        'factor_evidence' => [ClinicalFactorEvidence::forCode('BP-URG', [165, 110])],
    ]);

    foreach ($steps as $step) {
        expect($step->summary)->not->toContain('stopped because required records are missing');
        expect($step->summary)->not->toContain('The assessment stopped because');
    }

    expect(traceSummaryByCode($steps, 'COMPLETENESS_CHECK'))->toContain('preserved but did not block');
    expect($steps[6]->summary)->toContain('HIGH');
});

test('C: bp-h with missing records traces a completeness block with preserved bp alert', function () {
    $steps = traceSteps([
        'decision_source' => 'COMPLETENESS',
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'urgency' => 'PROMPT',
        'bp_assessment' => ['reason_code' => 'BP-H', 'label' => 'Elevated'],
        'missing_records' => ['Medical History'],
    ]);

    $statuses = array_map(static fn (DecisionTraceStep $s) => $s->status, $steps);

    expect($statuses)->toBe([
        'COMPLETED', 'COMPLETED', 'BLOCKED', 'SKIPPED', 'SKIPPED', 'SKIPPED', 'BLOCKED',
    ]);
    expect($steps[1]->summary)->toContain('BP-H');
    expect($steps[2]->missing_records)->toBe(['Medical History']);
    expect($steps[2]->summary)->toContain('elevated-BP alert was preserved');
});

test('D: rule-based high traces a triggered standalone step', function () {
    $steps = traceSteps([
        'decision_source' => 'RULE_BASED',
        'risk_level' => 'HIGH',
        'factor_evidence' => [ClinicalFactorEvidence::forCode('DM-01', true)],
    ]);

    $statuses = array_map(static fn (DecisionTraceStep $s) => $s->status, $steps);

    expect($statuses)->toBe([
        'COMPLETED', 'COMPLETED', 'COMPLETED', 'TRIGGERED', 'COMPLETED', 'SKIPPED', 'TRIGGERED',
    ]);
    expect($steps[3]->related_factor_codes)->toContain('DM-01');
    expect($steps[4]->related_interaction_codes)->toBe([]);
});

test('E: ml high traces a completed ml step and triggered final', function () {
    $steps = traceSteps([
        'decision_source' => 'MACHINE_LEARNING',
        'risk_level' => 'HIGH',
        'ml_prediction' => 'HIGH',
        'ml_valid' => true,
        'factor_evidence' => [],
    ]);

    $statuses = array_map(static fn (DecisionTraceStep $s) => $s->status, $steps);

    expect($statuses)->toBe([
        'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'TRIGGERED',
    ]);
    expect($steps[5]->summary)->toContain('HIGH');
});

test('F: ml low traces a completed ml step and completed final', function () {
    $steps = traceSteps([
        'decision_source' => 'MACHINE_LEARNING',
        'risk_level' => 'LOW',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'factor_evidence' => [],
    ]);

    $statuses = array_map(static fn (DecisionTraceStep $s) => $s->status, $steps);

    expect($statuses)->toBe([
        'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED',
    ]);
    expect($steps[5]->summary)->toContain('LOW');
    expect($steps[6]->summary)->toContain('LOW');
});

test('G: invalid ml traces a blocked ml step and blocked final', function () {
    $steps = traceSteps([
        'decision_source' => 'MACHINE_LEARNING_INVALID',
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'ml_prediction' => null,
        'ml_valid' => false,
        'factor_evidence' => [],
    ]);

    $statuses = array_map(static fn (DecisionTraceStep $s) => $s->status, $steps);

    expect($statuses)->toBe([
        'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'COMPLETED', 'BLOCKED', 'BLOCKED',
    ]);
    expect($steps[6]->summary)->toContain('ASSESSMENT INCOMPLETE');
});

test('every step carries the same assessed_at engine timestamp', function () {
    $steps = traceSteps(['assessed_at' => '2026-08-05T14:30:00+00:00']);

    foreach ($steps as $step) {
        expect($step->assessed_at)->toBe('2026-08-05T14:30:00+00:00');
    }
});

test('trace never includes technical output', function () {
    $steps = traceSteps();

    foreach ($steps as $step) {
        expect($step->summary)->not->toContain('Traceback');
        expect($step->summary)->not->toContain('raw_output');
        expect($step->summary)->not->toContain('Exception');
    }
});
