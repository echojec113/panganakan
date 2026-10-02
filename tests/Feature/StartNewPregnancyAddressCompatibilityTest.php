<?php

use App\Models\Patient;
use App\Models\User;

function startPregnancyStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

/**
 * A DELIVERED patient fixture for the Start New Pregnancy flow. Callers
 * override 'address' / 'address_line' / 'barangay' / 'city_municipality'
 * to exercise the legacy vs. structured address scenarios.
 */
function startPregnancyDeliveredPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Reyes',
        'birthdate' => today()->subYears(28)->toDateString(),
        'age' => 28,
        'contact_number' => '09171234567',
        'civil_status' => 'Married',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'status' => 'DELIVERED',
        'delivery_date' => today()->subDays(10)->toDateString(),
    ], $overrides));
}

it('pre-fills the Start New Pregnancy modal with the legacy address for a legacy-address patient', function () {
    $user = startPregnancyStaff();
    $patient = startPregnancyDeliveredPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->get(route('patients.delivered'));

    $response->assertOk();
    $response->assertSee('Santa Maria, Bulacan', false);
});

it('pre-fills the Start New Pregnancy modal with the formatted structured address instead of a blank legacy address', function () {
    $user = startPregnancyStaff();
    $patient = startPregnancyDeliveredPatient([
        'address' => null,
        'address_line' => 'Purok 2',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
    ]);

    $response = $this->actingAs($user)->get(route('patients.delivered'));

    $response->assertOk();

    // The modal prefill must not be blank for a structured-address patient,
    // and must use the centralized formatted_address (flattened to a single
    // line for the single-line modal input), not the empty legacy address.
    $response->assertSee('Purok 2, Barangay San Jose, San Jose del Monte', false);
});

it('creates the new pregnancy record correctly when the legacy patient address is submitted', function () {
    $user = startPregnancyStaff();
    $patient = startPregnancyDeliveredPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), [
        'lmp' => today()->subWeeks(10)->toDateString(),
        'edd' => today()->subWeeks(10)->addDays(280)->toDateString(),
        'address' => 'Santa Maria, Bulacan',
        'contact_number' => '09171234567',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $newPatient = Patient::where('status', 'ONGOING')->where('first_name', 'Maria')->firstOrFail();

    expect($newPatient->id)->not->toBe($patient->id);
    expect($newPatient->address)->toBe('Santa Maria, Bulacan');

    // The original delivered record must remain completely unchanged.
    $patient->refresh();
    expect($patient->address)->toBe('Santa Maria, Bulacan');
});

it('creates the new pregnancy record correctly when the flattened structured address is submitted, without corrupting the legacy column', function () {
    $user = startPregnancyStaff();
    $patient = startPregnancyDeliveredPatient([
        'address' => null,
        'address_line' => 'Purok 2',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
    ]);

    // This mirrors what the modal now submits: the formatted_address,
    // flattened to a single line (no embedded newlines).
    $flattenedAddress = str_replace("\n", ', ', $patient->formatted_address);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), [
        'lmp' => today()->subWeeks(10)->toDateString(),
        'edd' => today()->subWeeks(10)->addDays(280)->toDateString(),
        'address' => $flattenedAddress,
        'contact_number' => '09171234567',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $newPatient = Patient::where('status', 'ONGOING')->where('first_name', 'Maria')->firstOrFail();

    expect($newPatient->id)->not->toBe($patient->id);

    // The new patient's legacy `address` column receives a single-line
    // string — never a raw multiline formatted address.
    expect($newPatient->address)->toBe('Purok 2, Barangay San Jose, San Jose del Monte');
    expect($newPatient->address)->not->toContain("\n");

    // The structured-address patient that started the new pregnancy keeps
    // its own structured fields untouched.
    $patient->refresh();
    expect($patient->address)->toBeNull();
    expect($patient->address_line)->toBe('Purok 2');
    expect($patient->barangay)->toBe('San Jose');
    expect($patient->city_municipality)->toBe('San Jose del Monte');
});

it('still blocks Start New Pregnancy validation when the address is missing, regardless of address design', function () {
    $user = startPregnancyStaff();
    $patient = startPregnancyDeliveredPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), [
        'lmp' => today()->subWeeks(10)->toDateString(),
        'edd' => today()->subWeeks(10)->addDays(280)->toDateString(),
        'contact_number' => '09171234567',
    ]);

    $response->assertSessionHasErrors('address');
});
