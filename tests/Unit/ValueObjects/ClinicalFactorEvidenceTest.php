<?php

use App\ValueObjects\ClinicalFactorEvidence;

uses(Tests\TestCase::class);

test('forCode builds evidence for a registered code', function () {
    $evidence = ClinicalFactorEvidence::forCode('BP-H', ['initial' => '140/80']);

    expect($evidence->code)->toBe('BP-H');
    expect($evidence->category)->toBe('VITAL_SIGNS');
    expect($evidence->threshold_or_rule)->toBeString()->not->toBeEmpty();
    expect($evidence->decision_effect)->toContain('HIGH');
    expect($evidence->label)->toBeString()->not->toBeEmpty();
});

test('forCode overlays label, urgency, explanation over registry defaults', function () {
    $evidence = ClinicalFactorEvidence::forCode(
        'DM-01',
        'Yes',
        label: 'Overridden label',
        urgency: 'PROMPT',
        explanation: 'Custom explanation',
        suggestedAction: 'Custom action',
    );

    expect($evidence->label)->toBe('Overridden label');
    expect($evidence->urgency)->toBe('PROMPT');
    expect($evidence->explanation)->toBe('Custom explanation');
    expect($evidence->suggested_action)->toBe('Custom action');
});

test('unknown code throws OutOfBoundsException', function () {
    ClinicalFactorEvidence::forCode('MADE-UP-99', null);
})->throws(OutOfBoundsException::class);

test('direct construction with an unknown code fails', function () {
    new ClinicalFactorEvidence(
        code: 'NOT-REGISTERED',
        label: 'Invented factor',
        category: 'CURRENT_CONDITION',
        source_type: 'PATIENT',
        source_fields: ['x'],
        observed_value: 'Yes',
        threshold_or_rule: 'N/A',
        decision_effect: 'HIGH',
    );
})->throws(OutOfBoundsException::class);

test('direct construction with a registered code is permitted', function () {
    $evidence = new ClinicalFactorEvidence(
        code: 'DM-01',
        label: 'Diabetes',
        category: 'CURRENT_CONDITION',
        source_type: 'PRENATAL_VISIT',
        source_fields: ['diabetes'],
        observed_value: 'Yes',
        threshold_or_rule: 'Diabetes recorded present for the visit',
        decision_effect: 'HIGH',
    );

    expect($evidence->code)->toBe('DM-01');
    expect($evidence->observed_value)->toBe('Yes');
});

test('toArray contains only the approved keys', function () {
    $evidence = ClinicalFactorEvidence::forCode('CS-01', 'Yes');
    $array = $evidence->toArray();

    expect($array)->toHaveKeys([
        'code', 'label', 'category', 'source_type', 'source_fields',
        'observed_value', 'threshold_or_rule', 'decision_effect',
        'urgency', 'explanation', 'suggested_action',
    ]);
    expect($array)->toHaveCount(11);
});

test('normalizeList accepts objects, arrays, and null', function () {
    $evidence = ClinicalFactorEvidence::forCode('AN-01', 'Yes');

    expect(ClinicalFactorEvidence::normalizeList($evidence))->toHaveCount(1);
    expect(ClinicalFactorEvidence::normalizeList([$evidence->toArray()]))->toHaveCount(1);
    expect(ClinicalFactorEvidence::normalizeList(null))->toBe([]);
    expect(ClinicalFactorEvidence::normalizeList([]))->toBe([]);
    expect(ClinicalFactorEvidence::normalizeList('garbage'))->toBe([]);
});

test('normalizeList drops unknown keys from stored arrays', function () {
    $raw = ClinicalFactorEvidence::forCode('BP-H', ['initial' => '140/90'])->toArray();
    $raw['leaked_secret'] = 'do-not-render';

    $normalized = ClinicalFactorEvidence::normalizeList([$raw]);

    expect($normalized[0])->not->toHaveKey('leaked_secret');
});

test('normalizeList skips rows missing a required key', function () {
    $complete = ClinicalFactorEvidence::forCode('DM-01', 'Yes')->toArray();
    $missingLabel = $complete;
    unset($missingLabel['label']);

    $normalized = ClinicalFactorEvidence::normalizeList([$missingLabel, $complete]);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0]['code'])->toBe('DM-01');
});

test('normalizeList skips rows with an unregistered code', function () {
    $complete = ClinicalFactorEvidence::forCode('DM-01', 'Yes')->toArray();
    $unknownCode = $complete;
    $unknownCode['code'] = 'BOGUS-42';

    $normalized = ClinicalFactorEvidence::normalizeList([$unknownCode, $complete]);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0]['code'])->toBe('DM-01');
});

test('normalizeList keeps registered complete rows intact', function () {
    $raw = ClinicalFactorEvidence::forCode('CS-01', 'Yes')->toArray();

    $normalized = ClinicalFactorEvidence::normalizeList([$raw]);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0]['code'])->toBe('CS-01');
    expect($normalized[0]['label'])->toBeString()->not->toBeEmpty();
    expect($normalized[0]['category'])->toBeString()->not->toBeEmpty();
    expect($normalized[0]['source_type'])->toBeString()->not->toBeEmpty();
    expect($normalized[0]['decision_effect'])->toBeString()->not->toBeEmpty();
    expect($normalized[0]['observed_value'])->toBe('Yes');
});

test('object and resource observed values are replaced with a neutral value', function () {
    $evidence = ClinicalFactorEvidence::forCode('DM-01', new stdClass());
    expect($evidence->observed_value)->toBe('Recorded');

    $resource = fopen('php://memory', 'r');
    $withResource = ClinicalFactorEvidence::forCode('DM-01', $resource);
    expect($withResource->observed_value)->toBe('Recorded');
    fclose($resource);
});

test('eloquent model observed values are not serialized into evidence arrays', function () {
    $model = new stdClass();
    $model->first_name = 'Jane';
    $model->last_name = 'Doe';

    $evidence = ClinicalFactorEvidence::forCode('AN-01', $model);

    expect($evidence->observed_value)->toBe('Recorded');
    expect(json_encode($evidence->toArray()['observed_value']))->toBe('"Recorded"');
});

test('nested arrays in observed values remain readable', function () {
    $evidence = ClinicalFactorEvidence::forCode('BP-H', [
        'initial' => ['systolic' => 140, 'diastolic' => 90],
        'repeat' => ['systolic' => 145, 'diastolic' => 92],
    ]);

    expect($evidence->observed_value)->toBeArray();
    expect($evidence->observed_value['initial'])->toBe(['systolic' => 140, 'diastolic' => 90]);
    expect(ClinicalFactorEvidence::displayObserved($evidence->observed_value))
        ->toContain('Initial:')
        ->toContain('Systolic: 140')
        ->toContain('Diastolic: 90')
        ->toContain('Systolic: 145')
        ->toContain('Diastolic: 92');
});

test('underscored keys render as friendly title-cased labels', function () {
    expect(ClinicalFactorEvidence::displayObserved(['initial_bp' => '140/80', 'repeat_interpretation' => 'ELEVATED']))
        ->toContain('Initial BP: 140/80')
        ->toContain('Repeat Interpretation: ELEVATED');
});

test('displayObserved renders scalars, saves nested keys, and lists nested arrays', function () {
    expect(ClinicalFactorEvidence::displayObserved(null))->toBe('—');
    expect(ClinicalFactorEvidence::displayObserved(true))->toBe('Yes');
    expect(ClinicalFactorEvidence::displayObserved(false))->toBe('No');
    expect(ClinicalFactorEvidence::displayObserved('140/80'))->toBe('140/80');
    expect(ClinicalFactorEvidence::displayObserved(['initial' => '140/80', 'repeat' => '145/90']))
        ->toContain('Initial: 140/80')
        ->toContain('Repeat: 145/90');
    expect(ClinicalFactorEvidence::displayObserved(['a', 'b']))->toBe('a · b');
});