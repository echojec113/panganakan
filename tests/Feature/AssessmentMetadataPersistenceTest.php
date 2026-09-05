<?php

use App\Models\MedicalHistory;
use App\Models\BirthPlan;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Models\User;
use App\Services\BloodPressureAssessmentService;

function metadataPatient(): Patient
{
    return Patient::create([
        'first_name' => 'Jane', 'last_name' => 'Doe', 'age' => 28,
        'gravida' => 2, 'para' => 1,
        'status' => 'ONGOING',
    ]);
}

test('storing a visit with multiple active factors persists all factor evidence codes', function () {
    $patient = Patient::create([
        'first_name' => 'Maria', 'last_name' => 'Santos', 'age' => 30,
        'gravida' => 2, 'para' => 1,
        'status' => 'ONGOING',
        'previous_cs' => 1,
        'miscarriage' => 3,
    ]);
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Breech',
        'amniotic_fluid' => 'Low',
        'fetal_heartbeat' => 'Abnormal',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => true, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);

    $user = User::create([
        'name' => 'Staff',
        'email' => 'staff-multi@example.com',
        'password' => bcrypt('password'),
        'role' => 'staff',
    ]);

    $response = $this->actingAs($user)->post('/prenatal-visits', [
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 1,
        'anemia' => 1,
    ]);

    $response->assertSessionHasNoErrors();

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();

    expect($visit)->not->toBeNull();
    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->decision_source)->toBe('RULE_BASED');

    $codes = array_column($visit->factor_evidence, 'code');
    expect($codes)->toContain('DM-01');
    expect($codes)->toContain('AN-01');
    expect($codes)->toContain('CS-01');
    expect($codes)->toContain('RM-03');
    expect($codes)->toContain('US-P01');
    expect($codes)->toContain('US-AF01');
    expect($codes)->toContain('US-FH01');

    expect(count($visit->rule_reasons))->toBe(7);

    $trace = $visit->assessment_metadata['decision_trace'];
    $standalone = collect($trace)->firstWhere('step_code', 'STANDALONE_RULE_EVALUATION');
    expect($standalone['related_factor_codes'])->toContain('DM-01');
    expect($standalone['related_factor_codes'])->toContain('AN-01');
    expect($standalone['related_factor_codes'])->toContain('CS-01');
    expect($standalone['related_factor_codes'])->toContain('RM-03');
    expect($standalone['related_factor_codes'])->toContain('US-P01');
    expect($standalone['related_factor_codes'])->toContain('US-AF01');
    expect($standalone['related_factor_codes'])->toContain('US-FH01');

    $interactions = $visit->assessment_metadata['interaction_evidence'];
    expect($interactions)->toHaveCount(1);
    expect($interactions[0]['code'])->toBe('INT-CS-PRES');
    expect($interactions[0]['required_factor_codes'])->toBe(['CS-01', 'US-P01']);
    expect($interactions[0]['decision_effect'])->toBeNull();
    expect($interactions[0]['urgency'])->toBeNull();
    expect($visit->urgency)->toBeNull();
    expect($visit->ml_valid)->toBeFalse();
    expect($visit->ml_prediction)->toBeNull();
});

test('D: BP-H + diabetes + previous CS + breech persists both interactions once with trace codes', function () {
    $patient = Patient::create([
        'first_name' => 'Rosa', 'last_name' => 'Cruz', 'age' => 31,
        'gravida' => 3, 'para' => 2,
        'status' => 'ONGOING',
        'previous_cs' => 1,
        'miscarriage' => 0,
    ]);
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Breech',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);

    $user = User::create([
        'name' => 'Staff',
        'email' => 'staff-d@example.com',
        'password' => bcrypt('password'),
        'role' => 'staff',
    ]);

    $response = $this->actingAs($user)->post('/prenatal-visits', [
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 150,
        'bp_dia' => 92,
        'weight' => 62,
        'gestational_age' => 26,
        'hypertension' => 0,
        'diabetes' => 1,
        'anemia' => 0,
    ]);

    $response->assertSessionHasNoErrors();

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();
    expect($visit)->not->toBeNull();
    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->decision_source)->toBe('RULE_BASED');
    expect($visit->urgency)->toBe(BloodPressureAssessmentService::URGENCY_PROMPT);

    $codes = array_column($visit->factor_evidence, 'code');
    expect($codes)->toContain('BP-H');
    expect($codes)->toContain('DM-01');
    expect($codes)->toContain('CS-01');
    expect($codes)->toContain('US-P01');

    $interactions = $visit->assessment_metadata['interaction_evidence'];
    $codes = array_column($interactions, 'code');
    expect($codes)->toHaveCount(2);
    expect($codes)->toBe(['INT-BP-DM', 'INT-CS-PRES']);
    expect($codes)->toBe(array_unique($codes));

    $interactionStep = collect($visit->assessment_metadata['decision_trace'])
        ->firstWhere('step_code', 'INTERACTION_RULE_EVALUATION');
    expect($interactionStep['status'])->toBe('TRIGGERED');
    expect($interactionStep['related_interaction_codes'])->toBe(['INT-BP-DM', 'INT-CS-PRES']);
});

test('B-C: diabetes + high AF persists INT-DM-AF with HIGH context; low AF persists UIS-AF01 only', function () {
    $high = Patient::create([
        'first_name' => 'Alta', 'last_name' => 'Agua', 'age' => 27,
        'gravida' => 1, 'para' => 0, 'status' => 'ONGOING',
        'previous_cs' => 0, 'miscarriage' => 0,
    ]);
    Ultrasound::create([
        'patient_id' => $high->id,
        'scan_date' => now()->subDays(1)->toDateString(),
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'High',
        'fetal_heartbeat' => 'Normal',
    ]);
    MedicalHistory::create(['patient_id' => $high->id, 'diabetes' => true, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $high->id, 'deliver_in_clinic' => true]);

    $user = User::create([
        'name' => 'Staff', 'email' => 'staff-bc@example.com',
        'password' => bcrypt('password'), 'role' => 'staff',
    ]);

    $this->actingAs($user)->post('/prenatal-visits', [
        'patient_id' => $high->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 118, 'bp_dia' => 78,
        'weight' => 60, 'gestational_age' => 24,
        'hypertension' => 0, 'diabetes' => 1, 'anemia' => 0,
    ])->assertSessionHasNoErrors();

    $visit = PrenatalVisit::where('patient_id', $high->id)->first();
    expect($visit)->not->toBeNull();
    expect($visit->risk_level)->toBe('HIGH');

    $interactions = $visit->assessment_metadata['interaction_evidence'];
    $codes = array_column($interactions, 'code');
    expect($codes)->toContain('INT-DM-AF');

    $dmAf = collect($interactions)->firstWhere('code', 'INT-DM-AF');
    expect($dmAf['observed_context']['ultrasound_inputs.amniotic_fluid'])->toBe('HIGH');
    expect($dmAf['required_factor_codes'])->toBe(['DM-01', 'US-AF01']);
    expect($dmAf['decision_effect'])->toBeNull();
    expect($dmAf['urgency'])->toBeNull();
    expect($dmAf['rule_version'])->toBe('1.1.0');

    $traceStep = collect($visit->assessment_metadata['decision_trace'])
        ->firstWhere('step_code', 'INTERACTION_RULE_EVALUATION');
    expect($traceStep['related_interaction_codes'])->toBe(['INT-DM-AF']);

    // Low-fluid negative boundary
    $low = Patient::create([
        'first_name' => 'Baja', 'last_name' => 'Agua', 'age' => 30,
        'gravida' => 2, 'para' => 1, 'status' => 'ONGOING',
        'previous_cs' => 0, 'miscarriage' => 0,
    ]);
    Ultrasound::create([
        'patient_id' => $low->id,
        'scan_date' => now()->subDays(1)->toDateString(),
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Low',
        'fetal_heartbeat' => 'Normal',
    ]);
    MedicalHistory::create(['patient_id' => $low->id, 'diabetes' => true, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $low->id, 'deliver_in_clinic' => true]);

    $this->actingAs($user)->post('/prenatal-visits', [
        'patient_id' => $low->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120, 'bp_dia' => 80,
        'weight' => 58, 'gestational_age' => 24,
        'hypertension' => 0, 'diabetes' => 1, 'anemia' => 0,
    ])->assertSessionHasNoErrors();

    $visitLow = PrenatalVisit::where('patient_id', $low->id)->first();
    expect($visitLow)->not->toBeNull();
    expect(array_column($visitLow->factor_evidence, 'code'))->toContain('US-AF01');
    expect(
        array_column($visitLow->assessment_metadata['interaction_evidence'], 'code')
    )->not->toContain('INT-DM-AF');
});

test('storing a visit persists assessment metadata json', function () {
    $patient = metadataPatient();
    Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => true, 'anemia' => false]);
    BirthPlan::create(['patient_id' => $patient->id, 'deliver_in_clinic' => true]);

    $user = User::create([
        'name' => 'Staff',
        'email' => 'staff@example.com',
        'password' => bcrypt('password'),
        'role' => 'staff',
    ]);

    $response = $this->actingAs($user)->post('/prenatal-visits', [
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 1,
        'anemia' => 0,
    ]);

    $response->assertSessionHasNoErrors();

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();

    expect($visit)->not->toBeNull();
    expect($visit->assessment_metadata)->toBeArray();
    expect($visit->assessment_metadata['context']['patient_id'])->toBe((int) $patient->id);
    expect($visit->assessment_metadata['context']['prenatal_visit_id'])->toBe((int) $visit->id);
    expect($visit->assessment_metadata['interaction_evidence'])->toBe([]);
    expect($visit->assessment_metadata['decision_trace'])->toBeArray();
    expect(array_column($visit->assessment_metadata['decision_trace'], 'step_code'))->toBe([
        'CONTEXT_BUILT', 'URGENT_BP_CHECK', 'COMPLETENESS_CHECK', 'STANDALONE_RULE_EVALUATION',
        'INTERACTION_RULE_EVALUATION', 'ML_EVALUATION', 'FINAL_DECISION',
    ]);
    expect($visit->assessment_metadata['context']['ultrasound_inputs']['presentation'])->toBe('Cephalic');
    expect($visit->assessment_metadata['versions']['clinical_rules'])->toBe('1.1.0');
    expect($visit->assessment_metadata['assessed_at'])->not->toBeEmpty();
    expect($visit->assessment_metadata['context']['assessment_date'])->toBe(now()->toDateString());
    expect($visit->assessment_metadata['assessed_at'])->not->toBe($visit->assessment_metadata['context']['assessment_date']);
});

test('legacy visits without metadata keep a null assessment_metadata', function () {
    $patient = metadataPatient();
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120, 'bp_dia' => 80,
        'gestational_age' => 20,
    ]);

    expect($visit->assessment_metadata)->toBeNull();
});

test('patient show page renders the assessment context, verification, and decision path sections', function () {
    $patient = metadataPatient();

    PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => '2026-08-04',
        'bp_sys' => 120, 'bp_dia' => 80,
        'gestational_age' => 20,
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Diabetes'],
        'assessment_metadata' => [
            'context' => [
                'patient_id' => $patient->id,
                'assessment_date' => '2026-08-04',
                'ultrasound_date' => '2026-08-01',
                'medical_history_exists' => true,
                'birth_plan_exists' => true,
                'patient_status' => 'ONGOING',
            ],
            'interaction_evidence' => [],
            'data_quality_flags' => [
                [
                    'code' => 'DQ-ULTRASOUND-MISSING-FIELDS',
                    'label' => 'Evaluated ultrasound fields are missing',
                    'severity' => 'VERIFY',
                    'source_type' => 'ULTRASOUND',
                    'source_fields' => ['presentation'],
                    'observed_value' => null,
                    'expected_condition' => 'All evaluated ultrasound fields are recorded.',
                    'explanation' => 'One or more evaluated ultrasound fields are blank.',
                    'suggested_verification' => 'Complete the missing fields.',
                ],
            ],
            'decision_trace' => [
                [
                    'step_code' => 'STANDALONE_RULE_EVALUATION',
                    'status' => 'TRIGGERED',
                    'summary' => 'One or more ACTIVE deterministic factors triggered, resolving to HIGH.',
                    'related_factor_codes' => ['DM-01'],
                    'related_interaction_codes' => [],
                    'missing_records' => [],
                    'assessed_at' => '2026-08-04T10:00:00+00:00',
                ],
            ],
            'versions' => ['assessment_engine' => '1.0.0', 'clinical_rules' => '1.0.0', 'context' => 1],
            'assessed_at' => '2026-08-04T10:00:00+00:00',
        ],
    ]);

    $admin = User::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Assessment Context Used');
    $response->assertSee('Data Requiring Verification');
    $response->assertSee('Assessment Decision Path');
    $response->assertSee('Evaluated ultrasound fields are missing');
    $response->assertSee('One or more ACTIVE deterministic factors triggered');
});