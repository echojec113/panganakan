<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Services\AssessmentMetadataSerializer;
use App\ValueObjects\AssessmentResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('serializer produces a scoped metadata document', function () {
    $result = new AssessmentResult(
        risk_level: 'HIGH',
        assessment: 'High-risk pregnancy.',
        recommendation: 'Review recommended.',
        reasons: ['Diabetes'],
        nextVisit: CarbonImmutable::now()->addDays(3),
        decision_source: 'RULE_BASED',
        missing_records: [],
        rule_reasons: ['Diabetes'],
        ml_prediction: null,
        ml_valid: false,
        context: ['patient_id' => 1, 'assessment_date' => '2026-08-05'],
        data_quality_flags: [
            ['code' => 'DQ-DUP-MEDICAL-HISTORY', 'label' => 'More than one Medical History record', 'severity' => 'IMPORTANT', 'source_type' => 'MEDICAL_HISTORY', 'source_fields' => [], 'observed_value' => null, 'expected_condition' => 'x', 'explanation' => 'y', 'suggested_verification' => 'z'],
        ],
        decision_trace: [
            ['step_code' => 'STANDALONE_RULE_EVALUATION', 'status' => 'TRIGGERED', 'summary' => 'One or more ACTIVE deterministic factors triggered, resolving to HIGH.', 'related_factor_codes' => ['DM-01']],
        ],
        assessed_at: '2026-08-05T10:00:00+00:00',
    );

    $document = (new AssessmentMetadataSerializer)->fromResult($result);

    expect($document)->toHaveKeys([
        'context', 'interaction_evidence', 'data_quality_flags',
        'decision_trace', 'versions', 'assessed_at',
    ]);
    expect($document['context']['patient_id'])->toBe(1);
    expect($document['versions']['clinical_rules'])->toBe('1.1.0');
    expect($document['assessed_at'])->toBe('2026-08-05T10:00:00+00:00');
});

test('serializer patches the persisted visit into the context', function () {
    $patient = Patient::create([
        'first_name' => 'Jane', 'last_name' => 'Doe', 'age' => 28,
        'gravida' => 2, 'para' => 1,
        'status' => 'ONGOING',
    ]);
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => '2026-08-05',
        'bp_sys' => 120, 'bp_dia' => 80,
        'gestational_age' => 20,
    ]);

    $result = new AssessmentResult(
        risk_level: 'LOW',
        assessment: 'Low-risk pregnancy.',
        recommendation: 'Routine checkups.',
        reasons: [],
        nextVisit: CarbonImmutable::now()->addDays(30),
        decision_source: 'MACHINE_LEARNING',
        missing_records: [],
        rule_reasons: [],
        ml_prediction: 'LOW',
        ml_valid: true,
        context: ['patient_id' => $patient->id, 'prenatal_visit_id' => null],
    );

    $document = (new AssessmentMetadataSerializer)->fromResult($result, $visit);

    expect($document['context']['prenatal_visit_id'])->toBe((int) $visit->id);
    expect($document['context']['prenatal_visit_date'])->toBe('2026-08-05');
});