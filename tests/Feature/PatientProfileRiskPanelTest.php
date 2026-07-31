<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function riskPanelPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Panel',
        'last_name' => 'Test',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function riskPanelVisit(int $patientId, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
    ], $overrides));
}

it('shows the newest HIGH visit when multiple visits share the same visit date', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();

    $older = riskPanelVisit($patient->id, [
        'visit_date' => now()->toDateString(),
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Older LOW assessment text',
    ]);

    $newer = riskPanelVisit($patient->id, [
        'visit_date' => now()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'bp_assessment' => ['reason_code' => 'BP-URG', 'label' => 'Severe-range blood-pressure finding'],
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'assessment' => 'Newer HIGH assessment text',
    ]);

    expect($newer->id)->toBeGreaterThan($older->id);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('HIGH RISK');
    $response->assertSeeText('Newer HIGH assessment text');
    $response->assertSeeText('URGENT CLINICAL REVIEW');
    $response->assertDontSeeText('LOW RISK');
});

it('shows HIGH RISK and Rule-Based Clinical Assessment for a rule-based high visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();
    riskPanelVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
        'assessment' => 'High risk assessment',
        'recommendation' => 'Follow-up required.',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('HIGH RISK');
    $response->assertSeeText('Rule-Based Clinical Assessment');
    $response->assertSeeText('Hypertension');
});

it('shows URGENT CLINICAL REVIEW for a BP-URG visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();
    riskPanelVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'bp_sys' => 165,
        'bp_dia' => 110,
        'bp_assessment' => [
            'reason_code' => 'BP-URG',
            'label' => 'Severe-range blood-pressure finding',
            'verification_status' => 'PENDING_REPEAT',
            'repeat_interpretation' => 'NOT_RECORDED',
            'verification_note' => null,
        ],
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('URGENT CLINICAL REVIEW');
    $response->assertSeeText('HIGH RISK');
    $response->assertSeeText('Severe-range blood-pressure finding');
    $response->assertSeeText('Repeat Pending');
});

it('does not show a stale ML prediction for a rule-based high result', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();
    riskPanelVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Anemia'],
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('HIGH RISK');
    $response->assertSeeText('Rule-Based Clinical Assessment');
    $response->assertSeeText('Machine learning was not used for the final decision.');
    $response->assertSeeText('A deterministic clinical rule already determined the result.');
    $response->assertDontSeeText('Machine Learning Assessment');
});

it('shows LOW RISK and Machine Learning Assessment for a machine-learning low visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();
    riskPanelVisit($patient->id, [
        'risk_level' => 'LOW',
        'decision_source' => 'MACHINE_LEARNING',
        'ml_prediction' => 'LOW',
        'ml_valid' => true,
        'assessment' => 'Low risk assessment',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('LOW RISK');
    $response->assertSeeText('Machine Learning Assessment');
    $response->assertSeeText('Low risk assessment');
});

it('shows ASSESSMENT INCOMPLETE and missing records for a completeness visit', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();
    riskPanelVisit($patient->id, [
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'COMPLETENESS',
        'missing_records' => ['Ultrasound record', 'Birth plan record'],
        'assessment' => 'Incomplete assessment',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('ASSESSMENT INCOMPLETE');
    $response->assertSeeText('Required Records Still Missing');
    $response->assertSeeText('Ultrasound record');
    $response->assertSeeText('Birth plan record');
});

it('renders the panel with legacy plain-string rule reasons', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();
    riskPanelVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => 'Anemia risk noted',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Anemia risk noted');
});

it('renders the panel when bp_assessment is null', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();
    riskPanelVisit($patient->id, [
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Hypertension'],
        'bp_assessment' => null,
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('HIGH RISK');
});

it('shows NO ASSESSMENT AVAILABLE when the patient has no prenatal visits', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = riskPanelPatient();

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('NO ASSESSMENT AVAILABLE');
});
