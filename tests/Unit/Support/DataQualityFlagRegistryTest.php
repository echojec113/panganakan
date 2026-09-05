<?php

use App\Support\DataQualityFlagRegistry;

uses(Tests\TestCase::class);

test('four verification flags are active in sprint 13', function () {
    expect(DataQualityFlagRegistry::activeCodes())->toContain('DQ-SOURCE-FUTURE-DATED');
    expect(DataQualityFlagRegistry::activeCodes())->toContain('DQ-ULTRASOUND-MISSING-FIELDS');
    expect(DataQualityFlagRegistry::activeCodes())->toContain('DQ-DUP-MEDICAL-HISTORY');
    expect(DataQualityFlagRegistry::activeCodes())->toContain('DQ-DUP-BIRTH-PLAN');
    expect(DataQualityFlagRegistry::activeCodes())->toHaveCount(4);
});

test('active flag severities are informational verification levels only', function () {
    foreach (DataQualityFlagRegistry::activeCodes() as $code) {
        $metadata = DataQualityFlagRegistry::metadata($code);
        expect($metadata['severity'])->toBeIn([
            DataQualityFlagRegistry::SEVERITY_INFO,
            DataQualityFlagRegistry::SEVERITY_VERIFY,
            DataQualityFlagRegistry::SEVERITY_IMPORTANT,
        ]);
    }
});

test('deferred candidate flags are documented but never active', function () {
    foreach (['DQ-LMP-MISSING', 'DQ-EDD-MISSING', 'DQ-GA-DATE-MISMATCH', 'DQ-ULTRASOUND-STALE'] as $code) {
        expect(DataQualityFlagRegistry::isRegistered($code))->toBeTrue();
        expect(DataQualityFlagRegistry::isActive($code))->toBeFalse();
    }
});

test('isRegistered and metadata handle unknown codes safely', function () {
    expect(DataQualityFlagRegistry::isRegistered('DQ-NOT-A-FLAG'))->toBeFalse();
    expect(DataQualityFlagRegistry::metadata('DQ-NOT-A-FLAG'))->toBeNull();
});