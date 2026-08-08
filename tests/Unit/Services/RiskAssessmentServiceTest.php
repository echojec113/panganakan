<?php

use App\Models\Patient;
use App\Models\Ultrasound;
use App\Services\AssessmentContextBuilder;
use App\Services\AssessmentDataQualityService;
use App\Services\BloodPressureAssessmentService;
use App\Services\BloodPressureFactorEvidenceMapper;
use App\Services\ClinicalInteractionEngine;
use App\Services\ClinicalRuleEngine;
use App\Services\CompletenessValidator;
use App\Services\DecisionIntegrationService;
use App\Services\DecisionTraceBuilder;
use App\Services\MachineLearningService;
use App\Services\RiskAssessmentService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

function makePatient(): Patient
{
    return new Patient([
        'age' => 25, 'gravida' => 2, 'para' => 1,
        'previous_cs' => 0, 'miscarriage' => 0,
        'lmp' => now()->subWeeks(20)->toDateString(),
    ]);
}

function makeRiskAssessmentService(
    ?CompletenessValidator $completeness = null,
    ?ClinicalRuleEngine $ruleEngine = null,
    ?MachineLearningService $ml = null
): RiskAssessmentService {
    $completeness = $completeness ?? Mockery::mock(CompletenessValidator::class)
        ->shouldReceive('missingRequiredRecords')->andReturn([])->getMock();

    $ruleEngine = $ruleEngine ?? Mockery::mock(ClinicalRuleEngine::class)
        ->shouldReceive('evaluateDetailed')->andReturn([])->getMock();

    $ml = $ml ?? Mockery::mock(MachineLearningService::class)
        ->shouldReceive('predict')->andReturn([
            'valid' => false, 'prediction' => null,
        ])->getMock();

    return new RiskAssessmentService(
        $completeness,
        $ruleEngine,
        $ml,
        new DecisionIntegrationService,
        new BloodPressureAssessmentService,
        new BloodPressureFactorEvidenceMapper,
        new AssessmentContextBuilder,
        new ClinicalInteractionEngine,
        new AssessmentDataQualityService(new AssessmentContextBuilder),
        new DecisionTraceBuilder
    );
}

test('case 1: initial severe bp returns high urgent even with missing records', function () {
    $completeness = Mockery::mock(CompletenessValidator::class)
        ->shouldReceive('missingRequiredRecords')->andReturn(['Medical History'])->getMock();

    $service = makeRiskAssessmentService($completeness);

    $result = $service->assess(makePatient(), ['bp_sys' => 165, 'bp_dia' => 110]);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
    expect($result->urgency)->toBe('URGENT_CLINICAL_REVIEW');
    expect($result->missing_records)->toBe(['Medical History']);
    expect($result->bp_assessment['reason_code'])->toBe('BP-URG');
});

test('case 2: initial severe with normal repeat stays bp-urg urgent', function () {
    $service = makeRiskAssessmentService();

    $result = $service->assess(
        makePatient(),
        ['bp_sys' => 165, 'bp_dia' => 110],
        ['bp_sys' => 120, 'bp_dia' => 80]
    );

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
    expect($result->urgency)->toBe('URGENT_CLINICAL_REVIEW');
    expect($result->bp_assessment['reason_code'])->toBe('BP-URG');
});

test('case 3: normal initial with severe repeat returns bp-urg urgent', function () {
    $service = makeRiskAssessmentService();

    $result = $service->assess(
        makePatient(),
        ['bp_sys' => 120, 'bp_dia' => 80],
        ['bp_sys' => 170, 'bp_dia' => 115]
    );

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
    expect($result->urgency)->toBe('URGENT_CLINICAL_REVIEW');
    expect($result->bp_assessment['reason_code'])->toBe('BP-URG');
    expect($result->bp_assessment['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED);
});

test('case 8: bp-h with missing records stays assessment incomplete and preserves bp alert', function () {
    $completeness = Mockery::mock(CompletenessValidator::class)
        ->shouldReceive('missingRequiredRecords')->andReturn(['Medical History'])->getMock();

    $service = makeRiskAssessmentService($completeness);

    $result = $service->assess(makePatient(), ['bp_sys' => 140, 'bp_dia' => 80]);

    expect($result['risk_level'])->toBe('ASSESSMENT INCOMPLETE');
    expect($result['decision_source'])->toBe('COMPLETENESS');
    expect($result->bp_assessment['reason_code'])->toBe('BP-H');
});

test('case 9: bp-h with complete records and no other rules returns high rule based', function () {
    $service = makeRiskAssessmentService();

    $result = $service->assess(makePatient(), ['bp_sys' => 140, 'bp_dia' => 80]);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
    expect($result->bp_assessment['reason_code'])->toBe('BP-H');
    expect($result->urgency)->toBe(BloodPressureAssessmentService::URGENCY_PROMPT);
});

test('case 10: bp-h overrides a valid low ml prediction', function () {
    $ml = Mockery::mock(MachineLearningService::class)
        ->shouldReceive('predict')->andReturn([
            'valid' => true, 'prediction' => 'LOW',
        ])->getMock();

    $service = makeRiskAssessmentService(ml: $ml);

    $result = $service->assess(makePatient(), ['bp_sys' => 140, 'bp_dia' => 80]);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
    expect($result->bp_assessment['reason_code'])->toBe('BP-H');
    expect($result['ml_valid'])->toBeFalse();
});

test('case 11: bp-h overrides a valid high ml prediction as rule based', function () {
    $ml = Mockery::mock(MachineLearningService::class)
        ->shouldReceive('predict')->andReturn([
            'valid' => true, 'prediction' => 'HIGH',
        ])->getMock();

    $service = makeRiskAssessmentService(ml: $ml);

    $result = $service->assess(makePatient(), ['bp_sys' => 140, 'bp_dia' => 80]);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
    expect($result->bp_assessment['reason_code'])->toBe('BP-H');
});

test('ml is never invoked on the bp-urg urgent path', function () {
    $ml = Mockery::mock(MachineLearningService::class);
    $ml->shouldReceive('predict')->never();

    $completeness = Mockery::mock(CompletenessValidator::class)
        ->shouldReceive('missingRequiredRecords')->andReturn([])->getMock();

    $service = makeRiskAssessmentService($completeness, ml: $ml);

    $result = $service->assess(makePatient(), ['bp_sys' => 165, 'bp_dia' => 110]);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result->urgency)->toBe('URGENT_CLINICAL_REVIEW');
});

test('ml is never invoked on the complete bp-h path', function () {
    $ml = Mockery::mock(MachineLearningService::class);
    $ml->shouldReceive('predict')->never();

    $ruleEngine = Mockery::mock(ClinicalRuleEngine::class)
        ->shouldReceive('evaluateDetailed')->andReturn([])->getMock();

    $completeness = Mockery::mock(CompletenessValidator::class)
        ->shouldReceive('missingRequiredRecords')->andReturn([])->getMock();

    $service = makeRiskAssessmentService($completeness, $ruleEngine, $ml);

    $result = $service->assess(makePatient(), ['bp_sys' => 140, 'bp_dia' => 80]);

    expect($result['risk_level'])->toBe('HIGH');
    expect($result['decision_source'])->toBe('RULE_BASED');
    expect($result->bp_assessment['reason_code'])->toBe('BP-H');
});

test('unable to repeat status is server derived and requires a note', function () {
    $service = makeRiskAssessmentService();

    $result = $service->assess(
        makePatient(),
        ['bp_sys' => 140, 'bp_dia' => 80],
        null,
        BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT,
        'Patient declined to wait for repeat measurement'
    );

    expect($result->bp_assessment['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_UNABLE_TO_REPEAT);
});

test('forged repeat completed without pair resolves to pending repeat', function () {
    $service = makeRiskAssessmentService();

    $result = $service->assess(
        makePatient(),
        ['bp_sys' => 140, 'bp_dia' => 80],
        null,
        BloodPressureAssessmentService::VERIFICATION_REPEAT_COMPLETED
    );

    expect($result->bp_assessment['verification_status'])->toBe(BloodPressureAssessmentService::VERIFICATION_PENDING_REPEAT);
});

test('multi-factor deterministic assessment preserves every factor evidence code', function () {
    $patient = Patient::create([
        'first_name' => 'Multi', 'last_name' => 'Factor', 'age' => 30,
        'gravida' => 2, 'para' => 1, 'status' => 'ONGOING',
        'previous_cs' => 1, 'miscarriage' => 3,
    ]);

    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->toDateString(),
        'presentation' => 'Breech',
        'amniotic_fluid' => 'Low',
        'fetal_heartbeat' => 'Abnormal',
    ]);

    $service = makeRiskAssessmentService(ruleEngine: new ClinicalRuleEngine);

    $result = $service->assess($patient, [
        'bp_sys' => 120, 'bp_dia' => 80,
        'diabetes' => 1, 'anemia' => 1,
    ]);

    $codes = array_column($result->factor_evidence, 'code');

    expect($result->risk_level)->toBe('HIGH');
    expect($result->decision_source)->toBe('RULE_BASED');
    expect($codes)->toContain('DM-01');
    expect($codes)->toContain('AN-01');
    expect($codes)->toContain('CS-01');
    expect($codes)->toContain('RM-03');
    expect($codes)->toContain('US-P01');
    expect($codes)->toContain('US-AF01');
    expect($codes)->toContain('US-FH01');
    expect(array_column($result->interaction_evidence, 'code'))->toBe(['INT-CS-PRES']);
});

test('multiple active factors produce HIGH with no score or classification escalation', function () {
    $patient = Patient::create([
        'first_name' => 'Four', 'last_name' => 'Factors', 'age' => 29,
        'gravida' => 2, 'para' => 1, 'status' => 'ONGOING',
        'previous_cs' => 1, 'miscarriage' => 3,
    ]);

    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->toDateString(),
        'presentation' => 'Transverse',
        'amniotic_fluid' => 'High',
        'fetal_heartbeat' => 'Weak',
    ]);

    $service = makeRiskAssessmentService(ruleEngine: new ClinicalRuleEngine);

    $result = $service->assess($patient, [
        'bp_sys' => 120, 'bp_dia' => 80,
        'diabetes' => 1, 'anemia' => 1,
    ]);

    expect($result->risk_level)->toBe('HIGH');
    expect($result->decision_source)->toBe('RULE_BASED');
    expect($result->urgency)->toBeNull();

    $payload = $result->toArray();
    expect($payload)->not->toHaveKey('score');
    expect($payload)->not->toHaveKey('points');
    expect($payload)->not->toHaveKey('percentage');
    expect($result->risk_level)->not->toBe('VERY HIGH');
    expect($result->risk_level)->not->toBe('EXTREME');
});

test('deterministic multi-factor HIGH skips ML evaluation', function () {
    $patient = Patient::create([
        'first_name' => 'Override', 'last_name' => 'LOW', 'age' => 28,
        'gravida' => 2, 'para' => 1, 'status' => 'ONGOING',
        'previous_cs' => 1, 'miscarriage' => 3,
    ]);

    $ml = Mockery::mock(MachineLearningService::class);
    $ml->shouldReceive('predict')->never();

    $service = makeRiskAssessmentService(ruleEngine: new ClinicalRuleEngine, ml: $ml);

    $result = $service->assess($patient, [
        'bp_sys' => 120, 'bp_dia' => 80,
        'diabetes' => 1, 'anemia' => 1,
    ]);

    expect($result->risk_level)->toBe('HIGH');
    expect($result->decision_source)->toBe('RULE_BASED');
    expect($result->ml_valid)->toBeFalse();
    expect($result->ml_prediction)->toBeNull();
    expect(array_column($result->factor_evidence, 'code'))->toContain('DM-01');
    expect(array_column($result->factor_evidence, 'code'))->toContain('AN-01');
});

test('BP-URG plus other risk inputs stays HIGH urgent with no factor-count escalation', function () {
    $service = makeRiskAssessmentService();

    $result = $service->assess(makePatient(), [
        'bp_sys' => 165, 'bp_dia' => 110,
        'diabetes' => 1, 'anemia' => 1,
    ]);

    expect($result->risk_level)->toBe('HIGH');
    expect($result->decision_source)->toBe('RULE_BASED');
    expect($result->urgency)->toBe('URGENT_CLINICAL_REVIEW');
    expect($result->bp_assessment['reason_code'])->toBe('BP-URG');
    expect($result->risk_level)->not->toBe('EMERGENCY');

    $payload = $result->toArray();
    expect($payload)->not->toHaveKey('score');
    expect($payload)->not->toHaveKey('points');
    expect($payload)->not->toHaveKey('percentage');
});
