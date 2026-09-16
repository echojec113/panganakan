<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Support\PregnancyOutcomeVocabulary;

function activeListingUser(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function activeListingPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Ongoing',
        'last_name' => 'Patient',
        'birthdate' => '1994-02-10',
        'age' => 31,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

it('shows an ONGOING patient prenatal visit on the Prenatal Visits page', function () {
    $user = activeListingUser();
    $patient = activeListingPatient();

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 24,
        'risk_level' => 'LOW',
    ]);

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    $response->assertOk();
    $response->assertSee('Ongoing Patient');
    $response->assertSee(route('patients.show', ['patient' => $visit->patient_id, 'from' => 'prenatal-visits']));
});

it('hides a DELIVERED patient prenatal visit from the Prenatal Visits page but keeps the record intact', function () {
    $user = activeListingUser();
    $patient = activeListingPatient([
        'first_name' => 'Delivered',
        'last_name' => 'Patient',
    ]);

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 130,
        'bp_dia' => 85,
        'weight' => 62,
        'gestational_age' => 38,
        'risk_level' => 'HIGH',
        'risk_reasons' => ['PH'],
        'notes' => 'Original clinical notes.',
    ]);

    // Mark the patient as delivered through the real confirmed-delivery workflow.
    $this->actingAs($user)->post(route('patients.deliver', $patient->id), [
        'delivery_date' => now()->toDateString(),
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'babies' => [[
            'date_of_birth' => now()->toDateString(),
            'time_of_birth' => '09:30',
        ]],
    ])->assertSessionHasNoErrors();

    $patient->refresh();
    expect($patient->status)->toBe('DELIVERED');

    $response = $this->actingAs($user)->get(route('prenatal-visits.index'));

    $response->assertOk();
    $response->assertDontSee('Delivered Patient');

    // The prenatal visit record itself must still exist, unchanged, in the database.
    $visit->refresh();
    expect($visit)->not->toBeNull();
    expect($visit->patient_id)->toBe($patient->id);
    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->risk_reasons)->toBe(['PH']);
    expect($visit->notes)->toBe('Original clinical notes.');
    expect($visit->bp_sys)->toBe(130);
    expect($visit->bp_dia)->toBe(85);

    // Still accessible via the patient profile (historical access preserved).
    $profile = $this->actingAs($user)->get(route('patients.show', $patient->id));
    $profile->assertOk();
});

it('keeps search and risk-level filtering working for ONGOING patients after the status filter is applied', function () {
    $user = activeListingUser();
    $patientA = activeListingPatient(['first_name' => 'Alpha', 'last_name' => 'Visible']);
    $patientB = activeListingPatient(['first_name' => 'Beta', 'last_name' => 'Visible']);

    PrenatalVisit::create([
        'patient_id' => $patientA->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 58,
        'gestational_age' => 20,
        'risk_level' => 'HIGH',
    ]);

    PrenatalVisit::create([
        'patient_id' => $patientB->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 118,
        'bp_dia' => 76,
        'weight' => 55,
        'gestational_age' => 18,
        'risk_level' => 'LOW',
    ]);

    $html = $this->actingAs($user)->get(route('prenatal-visits.index'))->getContent();

    // Both ONGOING patients are present so client-side search/risk filtering
    // (which operates on the rows already rendered server-side) still has
    // both rows and their data attributes to filter against.
    expect($html)->toContain('Alpha Visible');
    expect($html)->toContain('Beta Visible');
    expect($html)->toContain('data-risk="HIGH"');
    expect($html)->toContain('data-risk="LOW"');
    expect($html)->toContain('data-name="alpha visible"');
    expect($html)->toContain('data-name="beta visible"');
});
