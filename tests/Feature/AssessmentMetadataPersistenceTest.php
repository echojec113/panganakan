<?php

use App\Models\MedicalHistory;
use App\Models\BirthPlan;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Models\User;

function metadataPatient(): Patient
{
    return Patient::create([
        'first_name' => 'Jane', 'last_name' => 'Doe', 'age' => 28,
        'gravida' => 2, 'para' => 1,
        'status' => 'ONGOING',
    ]);
}

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
    expect($visit->assessment_metadata['versions']['clinical_rules'])->toBe('1.0.0');
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