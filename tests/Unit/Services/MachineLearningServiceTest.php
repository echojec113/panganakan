<?php

use App\Models\Patient;
use App\Services\MachineLearningService;

uses(Tests\TestCase::class);

test('buildFeatureArray preserves exact 12-feature order', function () {
    $patient = new Patient([
        'age' => 25, 'gravida' => 2, 'para' => 1,
        'previous_cs' => 0, 'miscarriage' => 0,
    ]);

    $inputs = [
        'bp_sys' => 120, 'bp_dia' => 80,
        'weight' => 60, 'gestational_age' => 10,
        'hypertension' => 0, 'diabetes' => 0, 'anemia' => 0,
    ];

    $service = new MachineLearningService;
    $features = $service->buildFeatureArray($patient, $inputs);

    expect($features)->toBe([
        (float) 25,  // Age
        (float) 2,   // Gravida
        (float) 1,   // Parity
        (float) 120, // BP_sys
        (float) 80,  // BP_dia
        (float) 60,  // Weight
        (float) 10,  // Gestational_Age
        0,           // Hypertension
        0,           // Diabetes
        0,           // Prev_CS
        0,           // Miscarriage
        0,           // Anemia
    ]);
});

test('empty boolean values become 0', function () {
    $patient = new Patient([
        'age' => 25, 'gravida' => 1, 'para' => 0,
        'previous_cs' => null, 'miscarriage' => null,
    ]);

    $inputs = [
        'bp_sys' => 110, 'bp_dia' => 70,
        'weight' => 55, 'gestational_age' => 7,
        'hypertension' => '', 'diabetes' => null, 'anemia' => '',
    ];

    $service = new MachineLearningService;
    $features = $service->buildFeatureArray($patient, $inputs);

    expect($features[7])->toBe(0);  // hypertension
    expect($features[8])->toBe(0);  // diabetes
    expect($features[9])->toBe(0);  // previous_cs
    expect($features[10])->toBe(0); // miscarriage
    expect($features[11])->toBe(0); // anemia
});

test('boolean 1 becomes 1', function () {
    $patient = new Patient([
        'age' => 25, 'gravida' => 1, 'para' => 0,
        'previous_cs' => 1, 'miscarriage' => 2,
    ]);

    $inputs = [
        'bp_sys' => 110, 'bp_dia' => 70,
        'weight' => 55, 'gestational_age' => 7,
        'hypertension' => 1, 'diabetes' => 1, 'anemia' => 1,
    ];

    $service = new MachineLearningService;
    $features = $service->buildFeatureArray($patient, $inputs);

    expect($features[7])->toBe(1);  // hypertension
    expect($features[8])->toBe(1);  // diabetes
    expect($features[9])->toBe(1);  // previous_cs
    expect($features[10])->toBe(2); // miscarriage (int)
    expect($features[11])->toBe(1); // anemia
});

test('valid LOW output is recognized', function () {
    $service = new MachineLearningService;
    $result = $service->makeResult("some warnings\nLOW", 'LOW');

    expect($result['valid'])->toBeTrue();
    expect($result['prediction'])->toBe('LOW');
});

test('valid HIGH output is recognized', function () {
    $service = new MachineLearningService;
    $result = $service->makeResult("HIGH", 'HIGH');

    expect($result['valid'])->toBeTrue();
    expect($result['prediction'])->toBe('HIGH');
});

test('empty output is invalid', function () {
    $service = new MachineLearningService;
    $result = $service->makeResult('', '');

    expect($result['valid'])->toBeFalse();
    expect($result['prediction'])->toBeNull();
});

test('traceback or error output is invalid', function () {
    $service = new MachineLearningService;

    $tracebackResult = $service->makeResult(
        "Traceback (most recent call last):\n  File \"predict.py\", line 31, in <module>\n    values = list(map(float, sys.argv[1:]))\nValueError: could not convert string to float: ''",
        "Traceback (most recent call last):"
    );

    expect($tracebackResult['valid'])->toBeFalse();
    expect($tracebackResult['prediction'])->toBeNull();

    $errorResult = $service->makeResult(
        "Error: model file not found",
        "Error: model file not found"
    );

    expect($errorResult['valid'])->toBeFalse();
    expect($errorResult['prediction'])->toBeNull();
});

test('unexpected output such as MODERATE is invalid', function () {
    $service = new MachineLearningService;
    $result = $service->makeResult("MODERATE", 'MODERATE');

    expect($result['valid'])->toBeFalse();
    expect($result['prediction'])->toBeNull();
});

test('invalid output never becomes LOW', function () {
    $service = new MachineLearningService;

    $empty = $service->makeResult('', '');
    expect($empty['prediction'])->not->toBe('LOW');

    $error = $service->makeResult('ERROR: failed', 'ERROR: failed');
    expect($error['prediction'])->not->toBe('LOW');

    $moderate = $service->makeResult('MODERATE', 'MODERATE');
    expect($moderate['prediction'])->not->toBe('LOW');
});

test('predict with real low-risk profile returns LOW', function () {
    $patient = new Patient([
        'age' => 21, 'gravida' => 1, 'para' => 0,
        'previous_cs' => 0, 'miscarriage' => 0,
    ]);

    $inputs = [
        'bp_sys' => 120, 'bp_dia' => 80,
        'weight' => 55, 'gestational_age' => 7,
        'hypertension' => 0, 'diabetes' => 0, 'anemia' => 0,
    ];

    $service = new MachineLearningService;
    $result = $service->predict($patient, $inputs);

    expect($result['valid'])->toBeTrue();
    expect($result['prediction'])->toBe('LOW');
});

test('predict with high-risk profile returns HIGH', function () {
    $patient = new Patient([
        'age' => 40, 'gravida' => 3, 'para' => 2,
        'previous_cs' => 1, 'miscarriage' => 0,
    ]);

    $inputs = [
        'bp_sys' => 160, 'bp_dia' => 100,
        'weight' => 65, 'gestational_age' => 20,
        'hypertension' => 1, 'diabetes' => 1, 'anemia' => 1,
    ];

    $service = new MachineLearningService;
    $result = $service->predict($patient, $inputs);

    expect($result['valid'])->toBeTrue();
    expect($result['prediction'])->toBe('HIGH');
});
