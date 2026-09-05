<?php

use App\Support\ClinicalFactorRegistry;

uses(Tests\TestCase::class);

test('registry exposes the eleven allowed factor codes', function () {
    $codes = ClinicalFactorRegistry::codes();

    expect($codes)->toBeArray();
    expect($codes)->toContain('AGE-Y');
    expect($codes)->toContain('AGE-A');
    expect($codes)->toContain('BP-H');
    expect($codes)->toContain('BP-URG');
    expect($codes)->toContain('DM-01');
    expect($codes)->toContain('AN-01');
    expect($codes)->toContain('CS-01');
    expect($codes)->toContain('RM-03');
    expect($codes)->toContain('US-P01');
    expect($codes)->toContain('US-AF01');
    expect($codes)->toContain('US-FH01');
    expect($codes)->toHaveCount(11);
});

test('every registered code carries complete clinical metadata', function () {
    foreach (ClinicalFactorRegistry::all() as $code => $metadata) {
        expect($code)->toBeString();
        expect($metadata['label'])->toBeString()->not->toBeEmpty();
        expect($metadata['category'])->toBeString()->not->toBeEmpty();
        expect($metadata['source_type'])->toBeString()->not->toBeEmpty();
        expect($metadata['source_fields'])->toBeArray();
        expect($metadata['threshold_or_rule'])->toBeString()->not->toBeEmpty();
        expect($metadata['decision_effect'])->toContain('HIGH');
        expect($metadata['explanation'])->toBeString();
    }
});

test('isRegistered reports known and unknown codes', function () {
    expect(ClinicalFactorRegistry::isRegistered('BP-H'))->toBeTrue();
    expect(ClinicalFactorRegistry::isRegistered('NOT-A-CODE'))->toBeFalse();
});

test('metadata returns null for unknown codes (fail safe)', function () {
    expect(ClinicalFactorRegistry::metadata('NOPE-99'))->toBeNull();
});

test('all recognised ultrasound and obstetric factors are present', function () {
    $codes = ClinicalFactorRegistry::codes();
    $expected = ['US-P01', 'US-AF01', 'US-FH01', 'CS-01', 'RM-03'];
    foreach ($expected as $code) {
        expect($codes)->toContain($code);
    }
});