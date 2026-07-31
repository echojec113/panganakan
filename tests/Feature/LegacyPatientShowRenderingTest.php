<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function legacyShowPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Legacy',
        'last_name' => 'Show',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function legacyShowVisit(int $patientId, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'HIGH',
    ], $overrides));
}

it('renders risk reasons stored as an array', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = legacyShowPatient();
    legacyShowVisit($patient->id, [
        'risk_reasons' => ['Advanced maternal age', 'Gestational diabetes'],
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Advanced maternal age');
    $response->assertSeeText('Gestational diabetes');
});

it('renders risk reasons stored as a json string', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = legacyShowPatient();
    legacyShowVisit($patient->id, [
        'risk_reasons' => '["Prior cesarean delivery"]',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Prior cesarean delivery');
});

it('renders risk reasons stored as a plain string', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = legacyShowPatient();
    legacyShowVisit($patient->id, [
        'risk_reasons' => 'Multiple pregnancy',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Multiple pregnancy');
});

it('renders null risk reasons with a neutral fallback', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = legacyShowPatient();
    legacyShowVisit($patient->id, [
        'risk_reasons' => null,
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('No structured clinical factors recorded.');
});

it('renders legacy plain-string missing records and rule reasons', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = legacyShowPatient();
    legacyShowVisit($patient->id, [
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'COMPLETENESS',
        'missing_records' => 'Ultrasound record',
        'rule_reasons' => 'Birth plan record',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Ultrasound record');
    $response->assertSeeText('Birth plan record');
});

it('renders legacy json-string missing records with a fallback for empty data', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = legacyShowPatient();
    legacyShowVisit($patient->id, [
        'risk_level' => 'ASSESSMENT INCOMPLETE',
        'decision_source' => 'COMPLETENESS',
        'missing_records' => '["Laboratory results"]',
        'rule_reasons' => 'null',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Laboratory results');
});
