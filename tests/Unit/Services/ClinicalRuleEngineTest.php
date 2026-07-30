<?php

use App\Models\Patient;
use App\Models\Ultrasound;
use App\Services\ClinicalRuleEngine;

test('age 18 returns teenage pregnancy reason', function () {
    $patient = new Patient(['age' => 18, 'gravida' => 1, 'para' => 0]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe(['Teenage pregnancy (under 19)']);
});

test('age 35 gravida 1 para 0 returns advanced maternal age reason', function () {
    $patient = new Patient(['age' => 35, 'gravida' => 1, 'para' => 0]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe(['Advanced maternal age (35+) and first pregnancy']);
});

test('bp values do not trigger hypertension reason (moved to BP service)', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 2, 'para' => 1]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 140, 'bp_dia' => 90,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('severe bp values do not trigger bp reasons (moved to BP service)', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 2, 'para' => 1]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 160, 'bp_dia' => 110,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('diabetes returns diabetes reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 1, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe(['Diabetes']);
});

test('anemia returns anemia reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 1,
    ], null);

    expect($reasons)->toBe(['Anemia']);
});

test('previous cs returns previous cesarean reason', function () {
    $patient = new Patient(['age' => 30, 'gravida' => 2, 'para' => 1, 'previous_cs' => 1]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe(['Previous cesarean section']);
});

test('miscarriage 3 returns miscarriage reason', function () {
    $patient = new Patient(['age' => 32, 'gravida' => 5, 'para' => 2, 'miscarriage' => 3]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe(['History of 3 miscarriage(s)']);
});

test('breech presentation returns abnormal presentation reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = new Ultrasound(['presentation' => 'BREECH']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Abnormal fetal presentation (BREECH)']);
});

test('low amniotic fluid returns fluid abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = new Ultrasound(['amniotic_fluid' => 'LOW']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Amniotic fluid abnormality (LOW)']);
});

test('absent fetal heartbeat returns heartbeat abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = new Ultrasound(['fetal_heartbeat' => 'ABSENT']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Fetal heartbeat abnormality (ABSENT)']);
});

test('normal case returns empty array', function () {
    $patient = new Patient([
        'age' => 25, 'gravida' => 2, 'para' => 1,
        'previous_cs' => 0, 'miscarriage' => 0,
    ]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('duplicate reasons are removed', function () {
    $patient = new Patient(['age' => 18, 'gravida' => 1, 'para' => 0]);

    $ultrasound = new Ultrasound(['presentation' => 'BREECH']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 1,
        'anemia' => 1,
    ], $ultrasound);

    expect($reasons)->toBe([
        'Teenage pregnancy (under 19)',
        'Diabetes',
        'Anemia',
        'Abnormal fetal presentation (BREECH)',
    ]);
});
