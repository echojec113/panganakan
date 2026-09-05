<?php

use App\Support\ClinicalInteractionRegistry;

uses(Tests\TestCase::class);

test('sprint 15 activates exactly the three approved interactions', function () {
    expect(ClinicalInteractionRegistry::activeCodes())->toBe([
        'INT-BP-DM',
        'INT-DM-AF',
        'INT-CS-PRES',
    ]);
});

test('each active interaction carries the full controlled metadata contract', function () {
    foreach (['INT-BP-DM', 'INT-DM-AF', 'INT-CS-PRES'] as $code) {
        $metadata = ClinicalInteractionRegistry::metadata($code);
        expect($metadata)->not->toBeNull();
        expect($metadata['status'])->toBe(ClinicalInteractionRegistry::ACTIVE);
        expect($metadata['decision_effect'])->toBeNull();
        expect($metadata['urgency'])->toBeNull();
        expect($metadata['rule_version'])->toBe('1.1.0');
        expect(ClinicalInteractionRegistry::isActive($code))->toBeTrue();
    }
});

test('INT-BP-DM requires BP-H and DM-01', function () {
    expect(ClinicalInteractionRegistry::metadata('INT-BP-DM')['required_factor_codes'])->toBe(['BP-H', 'DM-01']);
});

test('INT-DM-AF requires DM-01 and US-AF01 and gates on high amniotic fluid', function () {
    $metadata = ClinicalInteractionRegistry::metadata('INT-DM-AF');
    expect($metadata['required_factor_codes'])->toBe(['DM-01', 'US-AF01']);
    expect($metadata['observed_value_conditions'])->toBe([
        'ultrasound_inputs.amniotic_fluid' => 'HIGH',
    ]);
    expect($metadata['observed_context_keys'])->toBe(['ultrasound_inputs.amniotic_fluid']);
});

test('INT-CS-PRES requires CS-01 and US-P01 and preserves the evaluated presentation', function () {
    $metadata = ClinicalInteractionRegistry::metadata('INT-CS-PRES');
    expect($metadata['required_factor_codes'])->toBe(['CS-01', 'US-P01']);
    expect($metadata['observed_context_keys'])->toBe(['ultrasound_inputs.presentation']);
});

test('INT-BP-DM declares no observed-context key to avoid duplicating BP data', function () {
    expect(ClinicalInteractionRegistry::metadata('INT-BP-DM')['observed_context_keys'] ?? [])->toBe([]);
});

test('draft and deferred interaction candidates are documented but not active', function () {
    $all = ClinicalInteractionRegistry::codes();

    expect($all)->toBeArray()->not->toBeEmpty();

    foreach ($all as $code) {
        $metadata = ClinicalInteractionRegistry::metadata($code);
        expect($metadata)->not->toBeNull();
        expect($metadata['status'])->toBeIn(['DRAFT', 'DEFERRED', 'ACTIVE']);
    }

    expect(ClinicalInteractionRegistry::isActive('INT-BP-DM'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isActive('INT-DM-AF'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isActive('INT-CS-PRES'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isActive('INT-WARNING-BP'))->toBeFalse();
    expect(ClinicalInteractionRegistry::isActive('INT-US-PRESENTATION-GA'))->toBeFalse();
    expect(ClinicalInteractionRegistry::isActive('INT-ANEMIA-LAB'))->toBeFalse();
    expect(ClinicalInteractionRegistry::isActive('INT-SYMPTOM-CONDITION'))->toBeFalse();
    expect(ClinicalInteractionRegistry::isActive('INT-PERSISTENT-FINDING'))->toBeFalse();
});

test('isRegistered reports known and unknown codes', function () {
    expect(ClinicalInteractionRegistry::isRegistered('INT-US-PRESENTATION-GA'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isRegistered('INT-BP-DM'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isRegistered('INT-DM-AF'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isRegistered('INT-CS-PRES'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isRegistered('NOT-AN-INTERACTION'))->toBeFalse();
});

test('metadata returns null for unknown codes', function () {
    expect(ClinicalInteractionRegistry::metadata('NOPE'))->toBeNull();
});