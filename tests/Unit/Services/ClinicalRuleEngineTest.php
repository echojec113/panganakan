<?php

use App\Models\Patient;
use App\Services\ClinicalRuleEngine;
use App\ValueObjects\ClinicalFactorEvidence;
use App\ValueObjects\UltrasoundSnapshot;

function usSnapshot(array $values): UltrasoundSnapshot
{
    return new UltrasoundSnapshot(
        id: null,
        date: null,
        presentation: (string) ($values['presentation'] ?? ''),
        amniotic_fluid: (string) ($values['amniotic_fluid'] ?? ''),
        fetal_heartbeat: (string) ($values['fetal_heartbeat'] ?? ''),
    );
}

test('evaluateDetailed returns structured evidence objects in rule order', function () {
    $patient = new Patient([
        'age' => 32, 'gravida' => 5, 'para' => 2,
        'previous_cs' => 1, 'miscarriage' => 3,
    ]);

    $ultrasound = usSnapshot(['presentation' => 'BREECH', 'amniotic_fluid' => 'LOW', 'fetal_heartbeat' => 'ABSENT']);

    $engine = new ClinicalRuleEngine;
    $evidence = $engine->evaluateDetailed($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 1, 'anemia' => 1,
    ], $ultrasound);

    $codes = array_map(
        static fn (ClinicalFactorEvidence $factor) => $factor->code,
        $evidence
    );

    expect($codes)->toBe(['DM-01', 'AN-01', 'CS-01', 'RM-03', 'US-P01', 'US-AF01', 'US-FH01']);
});

test('evaluate and evaluateDetailed stay consistent for the same input', function () {
    $patient = new Patient(['age' => 18, 'gravida' => 1, 'para' => 0]);
    $ultrasound = usSnapshot(['presentation' => 'BREECH']);

    $engine = new ClinicalRuleEngine;
    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);
    $evidence = $engine->evaluateDetailed($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);
    $labels = array_map(
        static fn (ClinicalFactorEvidence $factor) => $factor->label,
        $evidence
    );

    expect($reasons)->toBe($labels);
});

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

    $ultrasound = usSnapshot(['presentation' => 'BREECH']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Abnormal fetal presentation (BREECH)']);
});

test('low amniotic fluid returns fluid abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['amniotic_fluid' => 'LOW']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Amniotic fluid abnormality (LOW)']);
});

test('absent fetal heartbeat returns heartbeat abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['fetal_heartbeat' => 'ABSENT']);

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

    $ultrasound = usSnapshot(['presentation' => 'BREECH']);

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

test('warning symptom inputs are ignored by the rule engine', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 2, 'para' => 1]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0,
        'anemia' => 0,
        'severe_headache' => 1,
        'visual_disturbance' => 1,
        'chest_pain' => 1,
        'shortness_breath' => 1,
    ], null);

    expect($reasons)->toBe([]);
});

test('clinical rule engine consumes diabetes and anemia inputs only', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 2, 'para' => 1]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 1,
        'anemia' => 1,
        'severe_headache' => 1,
        'visual_disturbance' => 1,
        'chest_pain' => 1,
        'shortness_breath' => 1,
    ], null);

    expect($reasons)->toBe(['Diabetes', 'Anemia']);
});

test('previous cs 0 does not trigger previous cesarean reason', function () {
    $patient = new Patient(['age' => 30, 'gravida' => 2, 'para' => 1, 'previous_cs' => 0]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('previous cs null does not trigger previous cesarean reason', function () {
    $patient = new Patient(['age' => 30, 'gravida' => 2, 'para' => 1, 'previous_cs' => null]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('miscarriage 2 does not trigger recurrent miscarriage reason', function () {
    $patient = new Patient(['age' => 32, 'gravida' => 2, 'para' => 0, 'miscarriage' => 2]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('miscarriage 0 does not trigger recurrent miscarriage reason', function () {
    $patient = new Patient(['age' => 32, 'gravida' => 1, 'para' => 1, 'miscarriage' => 0]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('miscarriage null does not trigger recurrent miscarriage reason', function () {
    $patient = new Patient(['age' => 32, 'gravida' => 1, 'para' => 1, 'miscarriage' => null]);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], null);

    expect($reasons)->toBe([]);
});

test('transverse presentation returns abnormal presentation reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['presentation' => 'TRANSVERSE']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Abnormal fetal presentation (TRANSVERSE)']);
});

test('oblique presentation returns abnormal presentation reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['presentation' => 'OBLIQUE']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Abnormal fetal presentation (OBLIQUE)']);
});

test('cephalic presentation does not trigger abnormal presentation reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['presentation' => 'CEPHALIC']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe([]);
});

test('high amniotic fluid returns fluid abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['amniotic_fluid' => 'HIGH']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Amniotic fluid abnormality (HIGH)']);
});

test('normal amniotic fluid returns no fluid abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['amniotic_fluid' => 'NORMAL']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe([]);
});

test('weak fetal heartbeat returns heartbeat abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['fetal_heartbeat' => 'WEAK']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Fetal heartbeat abnormality (WEAK)']);
});

test('abnormal fetal heartbeat returns heartbeat abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['fetal_heartbeat' => 'ABNORMAL']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe(['Fetal heartbeat abnormality (ABNORMAL)']);
});

test('normal fetal heartbeat returns no heartbeat abnormality reason', function () {
    $patient = new Patient(['age' => 25, 'gravida' => 1, 'para' => 0]);

    $ultrasound = usSnapshot(['fetal_heartbeat' => 'NORMAL']);

    $engine = new ClinicalRuleEngine;

    $reasons = $engine->evaluate($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 0, 'anemia' => 0,
    ], $ultrasound);

    expect($reasons)->toBe([]);
});

test('multiple standalone factors coexist and all factor evidence is preserved', function () {
    $patient = new Patient([
        'age' => 25, 'gravida' => 2, 'para' => 1,
        'previous_cs' => 0, 'miscarriage' => 0,
    ]);

    $engine = new ClinicalRuleEngine;

    $evidence = $engine->evaluateDetailed($patient, [
        'bp_sys' => 110, 'bp_dia' => 70,
        'diabetes' => 1, 'anemia' => 1,
    ], null);

    $codes = array_map(
        static fn (ClinicalFactorEvidence $factor) => $factor->code,
        $evidence
    );

    expect($codes)->toBe(['DM-01', 'AN-01']);
});
