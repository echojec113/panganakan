<?php

use App\Models\Patient;
use App\Models\User;

function profileStartPregnancyStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

/**
 * A DELIVERED patient fixture for the patient-profile Start New Pregnancy
 * entry point. Callers override 'address' / 'address_line' / 'barangay' /
 * 'city_municipality' to exercise the legacy vs. structured scenarios.
 */
function profileStartPregnancyPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Carla',
        'last_name' => 'Santos',
        'birthdate' => today()->subYears(30)->toDateString(),
        'age' => 30,
        'contact_number' => '09181234567',
        'civil_status' => 'Married',
        'gravida' => 2,
        'para' => 1,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'status' => 'DELIVERED',
        'delivery_date' => today()->subDays(5)->toDateString(),
    ], $overrides));
}

it('no longer renders an incomplete direct-submit Start New Pregnancy form on the patient profile', function () {
    $user = profileStartPregnancyStaff();
    $patient = profileStartPregnancyPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();

    // The old broken entry point posted directly with only a CSRF token and
    // no lmp/edd/address/contact_number fields. It must be gone.
    $response->assertDontSee('onclick="return confirm(\'Start a new pregnancy record for this patient?', false);

    // The new entry point opens the same modal/workflow used on the
    // delivered-patients page, which exposes the required fields.
    $response->assertSee('openStartPregnancyModal(', false);
    $response->assertSee('name="lmp"', false);
    $response->assertSee('name="edd"', false);
    $response->assertSee('name="address"', false);
    $response->assertSee('name="contact_number"', false);
});

it('pre-fills the profile Start New Pregnancy modal with the legacy address for a legacy-address patient', function () {
    $user = profileStartPregnancyStaff();
    $patient = profileStartPregnancyPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Santa Maria, Bulacan', false);
});

it('pre-fills the profile Start New Pregnancy modal with the formatted structured address instead of a blank legacy address', function () {
    $user = profileStartPregnancyStaff();
    $patient = profileStartPregnancyPatient([
        'address' => null,
        'address_line' => 'Purok 2',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Purok 2, Barangay San Jose, San Jose del Monte', false);
});

it('successfully starts a new pregnancy through the profile flow using the existing controller behavior', function () {
    $user = profileStartPregnancyStaff();
    $patient = profileStartPregnancyPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), [
        'lmp' => today()->subWeeks(10)->toDateString(),
        'edd' => today()->subWeeks(10)->addDays(280)->toDateString(),
        'address' => 'Santa Maria, Bulacan',
        'contact_number' => '09181234567',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $newPatient = Patient::where('status', 'ONGOING')->where('first_name', 'Carla')->firstOrFail();

    expect($newPatient->id)->not->toBe($patient->id);
    expect($newPatient->gravida)->toBe(3);
    expect($newPatient->para)->toBe(1);
    expect($newPatient->address)->toBe('Santa Maria, Bulacan');
});

it('does not modify the originating patient legacy or structured address fields when starting a new pregnancy from the profile', function () {
    $user = profileStartPregnancyStaff();
    $patient = profileStartPregnancyPatient([
        'address' => null,
        'address_line' => 'Purok 2',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
    ]);

    $flattenedAddress = str_replace("\n", ', ', $patient->formatted_address);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), [
        'lmp' => today()->subWeeks(10)->toDateString(),
        'edd' => today()->subWeeks(10)->addDays(280)->toDateString(),
        'address' => $flattenedAddress,
        'contact_number' => '09181234567',
    ]);

    $response->assertSessionHasNoErrors();

    $patient->refresh();
    expect($patient->address)->toBeNull();
    expect($patient->address_line)->toBe('Purok 2');
    expect($patient->barangay)->toBe('San Jose');
    expect($patient->city_municipality)->toBe('San Jose del Monte');

    $newPatient = Patient::where('status', 'ONGOING')->where('first_name', 'Carla')->firstOrFail();
    expect($newPatient->address)->toBe('Purok 2, Barangay San Jose, San Jose del Monte');
});

it('still enforces required-field validation when the profile flow omits lmp/edd/address/contact_number', function () {
    $user = profileStartPregnancyStaff();
    $patient = profileStartPregnancyPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), []);

    $response->assertSessionHasErrors(['lmp', 'edd', 'address', 'contact_number']);
    expect(Patient::where('status', 'ONGOING')->exists())->toBeFalse();
});

it('keeps the delivered-patients list Start New Pregnancy flow working unchanged', function () {
    $user = profileStartPregnancyStaff();
    $patient = profileStartPregnancyPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->get(route('patients.delivered'));

    $response->assertOk();
    $response->assertSee('openStartPregnancyModal(', false);
    $response->assertSee('Santa Maria, Bulacan', false);
});
