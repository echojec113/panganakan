<?php

use App\Services\DecisionIntegrationService;
use App\ValueObjects\AssessmentResult;
use App\ValueObjects\ClinicalFactorEvidence;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

function makeFullResult(): AssessmentResult
{
    return new AssessmentResult(
        risk_level: 'HIGH',
        assessment: 'High-risk pregnancy.',
        recommendation: 'Referral recommended.',
        reasons: ['Hypertension', 'Diabetes'],
        nextVisit: CarbonImmutable::now()->addDays(3),
        decision_source: 'RULE_BASED',
        missing_records: [],
        rule_reasons: ['Hypertension', 'Diabetes'],
        ml_prediction: 'HIGH',
        ml_valid: true,
    );
}

test('exposes all twelve typed properties', function () {
    $result = makeFullResult();

    expect($result->risk_level)->toBeString();
    expect($result->assessment)->toBeString();
    expect($result->recommendation)->toBeString();
    expect($result->reasons)->toBeArray();
    expect($result->nextVisit)->toBeInstanceOf(CarbonImmutable::class);
    expect($result->decision_source)->toBeString();
    expect($result->missing_records)->toBeArray();
    expect($result->rule_reasons)->toBeArray();
    expect($result->ml_prediction)->toBeString();
    expect($result->ml_valid)->toBeTrue();
    expect($result->urgency)->toBeNull();
    expect($result->bp_assessment)->toBeNull();
    expect($result->factor_evidence)->toBeArray();
});

test('toArray contains exactly thirteen approved keys', function () {
    $result = makeFullResult();
    $array = $result->toArray();

    $approved = ['risk_level', 'assessment', 'recommendation', 'reasons', 'nextVisit',
        'decision_source', 'missing_records', 'rule_reasons', 'ml_prediction', 'ml_valid',
        'urgency', 'bp_assessment', 'factor_evidence'];

    foreach ($approved as $key) {
        expect($array)->toHaveKey($key);
    }

    expect($array)->toHaveCount(13);
});

test('property access and array access return identical values', function () {
    $result = makeFullResult();

    expect($result['risk_level'])->toBe($result->risk_level);
    expect($result['assessment'])->toBe($result->assessment);
    expect($result['recommendation'])->toBe($result->recommendation);
    expect($result['reasons'])->toBe($result->reasons);
    expect($result['nextVisit'])->toBe($result->nextVisit);
    expect($result['decision_source'])->toBe($result->decision_source);
    expect($result['missing_records'])->toBe($result->missing_records);
    expect($result['rule_reasons'])->toBe($result->rule_reasons);
    expect($result['ml_prediction'])->toBe($result->ml_prediction);
    expect($result['ml_valid'])->toBe($result->ml_valid);
    expect($result['urgency'])->toBe($result->urgency);
    expect($result['bp_assessment'])->toBe($result->bp_assessment);
    expect($result['factor_evidence'])->toBe($result->factor_evidence);
});

test('unknown key access throws OutOfBoundsException', function () {
    $result = makeFullResult();

    $result['nonexistent'];
})->throws(OutOfBoundsException::class);

test('array assignment throws LogicException', function () {
    $result = makeFullResult();

    $result['risk_level'] = 'LOW';
})->throws(LogicException::class);

test('array unset throws LogicException', function () {
    $result = makeFullResult();

    unset($result['risk_level']);
})->throws(LogicException::class);

test('nextVisit is CarbonImmutable', function () {
    $result = makeFullResult();

    expect($result->nextVisit)->toBeInstanceOf(CarbonImmutable::class);
});

test('immutable date operation returns new instance and does not alter original', function () {
    $result = makeFullResult();
    $original = $result->nextVisit;

    $modified = $original->addDays(5);

    expect($modified)->not->toBe($original);
    expect($result->nextVisit)->toBe($original);
});

test('every decision path returns AssessmentResult', function () {
    $service = new DecisionIntegrationService;

    $paths = [
        $service->decide(['Medical History'], [], null),
        $service->decide([], ['Diabetes'], null),
        $service->decide([], [], ['valid' => true, 'prediction' => 'HIGH']),
        $service->decide([], [], ['valid' => true, 'prediction' => 'LOW']),
        $service->decide([], [], ['valid' => false, 'prediction' => null]),
    ];

    foreach ($paths as $result) {
        expect($result)->toBeInstanceOf(AssessmentResult::class);
    }
});

test('serialization does not expose raw fields', function () {
    $result = makeFullResult();
    $array = $result->toArray();

    expect($array)->not->toHaveKey('raw_output');
    expect($array)->not->toHaveKey('parsed_output');
});

test('assessment result removes unknown keys from factor evidence', function () {
    $row = ClinicalFactorEvidence::forCode('DM-01', 'Yes')->toArray();
    $row['leaked_secret'] = 'never-render';

    $result = new AssessmentResult(
        risk_level: 'HIGH',
        assessment: 'High-risk pregnancy.',
        recommendation: 'Referral recommended.',
        reasons: ['Diabetes'],
        nextVisit: CarbonImmutable::now()->addDays(3),
        decision_source: 'RULE_BASED',
        missing_records: [],
        rule_reasons: ['Diabetes'],
        ml_prediction: 'HIGH',
        ml_valid: true,
        factor_evidence: [$row],
    );

    expect($result->factor_evidence)->toHaveCount(1);
    expect($result->factor_evidence[0])->not->toHaveKey('leaked_secret');
    expect($result['factor_evidence'][0])->not->toHaveKey('leaked_secret');
});

test('assessment result skips an unregistered factor code', function () {
    $good = ClinicalFactorEvidence::forCode('DM-01', 'Yes')->toArray();
    $unknown = $good;
    $unknown['code'] = 'BOGUS-99';

    $result = new AssessmentResult(
        risk_level: 'HIGH',
        assessment: 'High-risk pregnancy.',
        recommendation: 'Referral recommended.',
        reasons: ['Diabetes'],
        nextVisit: CarbonImmutable::now()->addDays(3),
        decision_source: 'RULE_BASED',
        missing_records: [],
        rule_reasons: ['Diabetes'],
        ml_prediction: 'HIGH',
        ml_valid: true,
        factor_evidence: [$unknown, $good],
    );

    expect($result->factor_evidence)->toHaveCount(1);
    expect($result->factor_evidence[0]['code'])->toBe('DM-01');
});

test('assessment result skips incomplete factor rows', function () {
    $good = ClinicalFactorEvidence::forCode('DM-01', 'Yes')->toArray();
    $incomplete = $good;
    unset($incomplete['category']);

    $result = new AssessmentResult(
        risk_level: 'HIGH',
        assessment: 'High-risk pregnancy.',
        recommendation: 'Referral recommended.',
        reasons: ['Diabetes'],
        nextVisit: CarbonImmutable::now()->addDays(3),
        decision_source: 'RULE_BASED',
        missing_records: [],
        rule_reasons: ['Diabetes'],
        ml_prediction: 'HIGH',
        ml_valid: true,
        factor_evidence: [$incomplete, $good],
    );

    expect($result->factor_evidence)->toHaveCount(1);
    expect($result->factor_evidence[0]['code'])->toBe('DM-01');
});

test('assessment result keeps registered complete factor rows intact', function () {
    $good = ClinicalFactorEvidence::forCode('CS-01', 'Yes')->toArray();

    $result = new AssessmentResult(
        risk_level: 'HIGH',
        assessment: 'High-risk pregnancy.',
        recommendation: 'Referral recommended.',
        reasons: ['Previous cesarean section'],
        nextVisit: CarbonImmutable::now()->addDays(3),
        decision_source: 'RULE_BASED',
        missing_records: [],
        rule_reasons: ['Previous cesarean section'],
        ml_prediction: 'HIGH',
        ml_valid: true,
        factor_evidence: [$good],
    );

    expect($result->factor_evidence)->toHaveCount(1);
    expect($result->factor_evidence[0]['code'])->toBe('CS-01');
    expect($result->factor_evidence[0]['observed_value'])->toBe('Yes');
});
