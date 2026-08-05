<?php

use App\Support\ClinicalInteractionRegistry;

uses(Tests\TestCase::class);

test('sprint 13 ships zero active interactions by design', function () {
    expect(ClinicalInteractionRegistry::activeCodes())->toBe([]);
});

test('draft and deferred interaction candidates are documented but not active', function () {
    $all = ClinicalInteractionRegistry::codes();

    expect($all)->toBeArray()->not->toBeEmpty();

    foreach ($all as $code) {
        $metadata = ClinicalInteractionRegistry::metadata($code);
        expect($metadata)->not->toBeNull();
        expect($metadata['status'])->toBeIn(['DRAFT', 'DEFERRED']);
        expect(ClinicalInteractionRegistry::isActive($code))->toBeFalse();
    }
});

test('isRegistered reports known and unknown codes', function () {
    expect(ClinicalInteractionRegistry::isRegistered('INT-US-PRESENTATION-GA'))->toBeTrue();
    expect(ClinicalInteractionRegistry::isRegistered('NOT-AN-INTERACTION'))->toBeFalse();
});

test('metadata returns null for unknown codes', function () {
    expect(ClinicalInteractionRegistry::metadata('NOPE'))->toBeNull();
});