<?php

use App\Support\DataQualityFlagRegistry;
use App\ValueObjects\DataQualityFlag;

uses(Tests\TestCase::class);

test('flag carries the approved fields only', function () {
    $flag = new DataQualityFlag(
        code: 'DQ-DUP-MEDICAL-HISTORY',
        label: 'More than one Medical History record',
        severity: DataQualityFlagRegistry::SEVERITY_IMPORTANT,
        source_type: DataQualityFlagRegistry::SOURCE_MEDICAL_HISTORY,
        source_fields: ['patient_id'],
        observed_value: ['active_record_count' => 2],
        expected_condition: 'Exactly one active Medical History record per pregnancy.',
        explanation: 'More than one active Medical History record exists.',
        suggested_verification: 'Review and reconcile the duplicate records.',
    );

    $array = $flag->toArray();

    expect($array['code'])->toBe('DQ-DUP-MEDICAL-HISTORY');
    expect($array['observed_value']['active_record_count'])->toBe(2);
    expect($array['severity'])->toBe('IMPORTANT');
});

test('unknown flag code throws OutOfBoundsException', function () {
    new DataQualityFlag(
        code: 'DQ-NOT-A-FLAG',
        label: 'Nope',
        severity: 'INFO',
        source_type: 'PATIENT',
        source_fields: [],
        observed_value: null,
        expected_condition: 'x',
        explanation: 'y',
    );
})->throws(OutOfBoundsException::class);

test('invalid severity throws OutOfBoundsException', function () {
    new DataQualityFlag(
        code: 'DQ-DUP-BIRTH-PLAN',
        label: 'More than one Birth Plan record',
        severity: 'HIGH',
        source_type: 'BIRTH_PLAN',
        source_fields: [],
        observed_value: null,
        expected_condition: 'x',
        explanation: 'y',
    );
})->throws(OutOfBoundsException::class);

test('normalizeList keeps approved keys and drops unknown ones', function () {
    $normalized = DataQualityFlag::normalizeList([
        [
            'code' => 'DQ-SOURCE-FUTURE-DATED',
            'label' => 'Ultrasound scan date is after the assessment date',
            'severity' => 'VERIFY',
            'source_type' => 'ULTRASOUND',
            'source_fields' => ['scan_date'],
            'observed_value' => ['ultrasound_date' => '2026-08-06'],
            'expected_condition' => 'x',
            'explanation' => 'y',
            'suggested_verification' => 'z',
            'clinical_impact' => 'dropped',
        ],
    ]);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0])->not->toHaveKey('clinical_impact');
});

test('normalizeList skips unregistered and malformed rows', function () {
    $normalized = DataQualityFlag::normalizeList([
        ['code' => 'DQ-UNREGISTERED', 'label' => 'x', 'severity' => 'INFO'],
        ['code' => 'DQ-LMP-MISSING', 'label' => 'Missing severity'],
        'not-an-array',
    ]);

    expect($normalized)->toBe([]);
});

test('normalizeList accepts a single flag object', function () {
    $flag = new DataQualityFlag(
        code: 'DQ-ULTRASOUND-MISSING-FIELDS',
        label: 'Evaluated ultrasound fields are missing',
        severity: 'VERIFY',
        source_type: 'ULTRASOUND',
        source_fields: ['presentation'],
        observed_value: null,
        expected_condition: 'x',
        explanation: 'y',
    );

    $normalized = DataQualityFlag::normalizeList($flag);

    expect($normalized)->toHaveCount(1);
    expect($normalized[0]['code'])->toBe('DQ-ULTRASOUND-MISSING-FIELDS');
});