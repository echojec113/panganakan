<?php

use App\Models\Patient;
use App\Models\User;
use App\Models\MedicalHistory;

function structuredAddressStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

/**
 * Base payload for patients.store, without any address component. Tests
 * merge in the address fields they want to exercise.
 */
function structuredAddressStorePayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ana',
        'middle_name' => '',
        'last_name' => 'Reyes',
        'birthdate' => '1995-01-01',
        'age' => 31,
        'contact_number' => '09171234567',
        'email' => 'ana@example.com',
        'civil_status' => 'Single',
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
    ], $overrides);
}

function structuredAddressPatientFixture(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Legacy',
        'last_name' => 'Patient',
        'birthdate' => today()->subYears(28)->toDateString(),
        'age' => 28,
        'address' => 'Santa Maria',
        'contact_number' => '09171234567',
        'civil_status' => 'Single',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
        'status' => 'ONGOING',
    ], $overrides));
}

// 1 & 12. New patient stores all 3 structured fields; all 3 are required.
it('stores all 3 structured address fields for a new patient', function () {
    $user = structuredAddressStaff();

    $response = $this->actingAs($user)->post(route('patients.store'), structuredAddressStorePayload([
        'address_line' => '123 Mabini St., Green Village',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
    ]));

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect($patient->address_line)->toBe('123 Mabini St., Green Village');
    expect($patient->barangay)->toBe('San Jose');
    expect($patient->city_municipality)->toBe('San Jose del Monte');
    // No duplicated combined value is ever written to the legacy column.
    expect($patient->address)->toBeNull();
});

it('requires address_line, barangay, and city_municipality for a new patient', function () {
    $user = structuredAddressStaff();

    $response = $this->actingAs($user)->post(route('patients.store'), structuredAddressStorePayload());

    $response->assertSessionHasErrors(['address_line', 'barangay', 'city_municipality']);
});

// 2 & 3 & 4. Address Line is free-text and never requires every placeholder
// component (house number, street, subdivision) to be present together.
it('accepts a simple single-word Address Line such as "Purok 2"', function () {
    $user = structuredAddressStaff();

    $response = $this->actingAs($user)->post(route('patients.store'), structuredAddressStorePayload([
        'address_line' => 'Purok 2',
        'barangay' => 'San Jose',
        'city_municipality' => 'Santa Maria',
    ]));

    $response->assertRedirect(route('patients.index'));
    expect(Patient::latest('id')->first()->address_line)->toBe('Purok 2');
});

it('accepts an Address Line containing commas, such as "Blk 2 Lot 5, Sampaguita Village"', function () {
    $user = structuredAddressStaff();

    $response = $this->actingAs($user)->post(route('patients.store'), structuredAddressStorePayload([
        'address_line' => 'Blk 2 Lot 5, Sampaguita Village',
        'barangay' => 'San Jose',
        'city_municipality' => 'Santa Maria',
    ]));

    $response->assertRedirect(route('patients.index'));
    expect(Patient::latest('id')->first()->address_line)->toBe('Blk 2 Lot 5, Sampaguita Village');
});

it('does not require a house number, street, or subdivision together in Address Line', function () {
    $user = structuredAddressStaff();

    foreach (['Sitio Riverside', 'Phase 2, Block 4', 'Unit 4, ABC Building'] as $value) {
        $response = $this->actingAs($user)->post(route('patients.store'), structuredAddressStorePayload([
            'address_line' => $value,
            'barangay' => 'San Jose',
            'city_municipality' => 'Santa Maria',
        ]));

        $response->assertRedirect(route('patients.index'));
    }

    expect(Patient::where('address_line', 'Sitio Riverside')->exists())->toBeTrue();
    expect(Patient::where('address_line', 'Phase 2, Block 4')->exists())->toBeTrue();
    expect(Patient::where('address_line', 'Unit 4, ABC Building')->exists())->toBeTrue();
});

// 5 & 7. Structured address displays correctly; "Barangay" is prepended at
// display time only (never stored in the database).
it('formats a fully structured address with the Barangay label added for display only', function () {
    $patient = structuredAddressPatientFixture([
        'address' => null,
        'address_line' => '123 Mabini St., Green Village',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
    ]);

    expect($patient->barangay)->toBe('San Jose'); // stored without the word "Barangay"
    expect($patient->formatted_address)->toBe("123 Mabini St., Green Village\nBarangay San Jose\nSan Jose del Monte");
});

// 6. Missing components do not create empty lines or dangling punctuation.
it('omits blank lines when a structured address component is missing', function () {
    $patient = structuredAddressPatientFixture([
        'address' => null,
        'address_line' => 'Purok 2',
        'barangay' => null,
        'city_municipality' => 'Santa Maria',
    ]);

    expect($patient->formatted_address)->toBe("Purok 2\nSanta Maria");
    expect($patient->formatted_address)->not->toContain("\n\n");
    expect($patient->formatted_address)->not->toContain('Barangay ,');
});

// 8. Legacy patient with only the old `address` still displays correctly.
it('falls back to the legacy address when no structured fields are set', function () {
    $patient = structuredAddressPatientFixture([
        'address' => 'Gubat Street Parada Santa Maria Bulacan',
    ]);

    expect($patient->address_line)->toBeNull();
    expect($patient->formatted_address)->toBe('Gubat Street Parada Santa Maria Bulacan');
});

// 9. Legacy patient remains editable without being forced to add a
// structured address, and 10. editing unrelated info never erases the
// legacy address.
it('allows editing a legacy patient without requiring structured address fields', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => 'Santa Maria',
    ]);

    $response = $this->actingAs($user)->patch(route('patients.update', $patient->id), [
        'first_name' => $patient->first_name,
        'last_name' => $patient->last_name,
        'birthdate' => $patient->birthdate->toDateString(),
        'age' => $patient->age,
        'contact_number' => '09181234567', // the "unrelated" field being changed
        'civil_status' => $patient->civil_status,
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => $patient->gravida,
        'para' => $patient->para,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => $patient->lmp->toDateString(),
        'edd' => $patient->edd->toDateString(),
    ]);

    $response->assertRedirect(route('patients.index'));
    $response->assertSessionDoesntHaveErrors();

    $patient->refresh();

    expect($patient->contact_number)->toBe('09181234567');
    // Legacy address is never erased or overwritten by an unrelated edit.
    expect($patient->address)->toBe('Santa Maria');
    expect($patient->address_line)->toBeNull();
});

it('requires all 3 structured fields together once any one of them is filled in on edit', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture();

    $response = $this->actingAs($user)->patch(route('patients.update', $patient->id), [
        'first_name' => $patient->first_name,
        'last_name' => $patient->last_name,
        'birthdate' => $patient->birthdate->toDateString(),
        'age' => $patient->age,
        'address_line' => '123 Mabini St.',
        // barangay and city_municipality intentionally left blank
        'contact_number' => $patient->contact_number,
        'civil_status' => $patient->civil_status,
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => $patient->gravida,
        'para' => $patient->para,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => $patient->lmp->toDateString(),
        'edd' => $patient->edd->toDateString(),
    ]);

    $response->assertSessionHasErrors(['barangay', 'city_municipality']);
});

// 11. Structured address takes display precedence once supplied.
it('gives the structured address display precedence over the legacy address once supplied', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => 'Santa Maria',
    ]);

    $response = $this->actingAs($user)->patch(route('patients.update', $patient->id), [
        'first_name' => $patient->first_name,
        'last_name' => $patient->last_name,
        'birthdate' => $patient->birthdate->toDateString(),
        'age' => $patient->age,
        'address_line' => '123 Mabini St., Green Village',
        'barangay' => 'San Jose',
        'city_municipality' => 'San Jose del Monte',
        'contact_number' => $patient->contact_number,
        'civil_status' => $patient->civil_status,
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => $patient->gravida,
        'para' => $patient->para,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => $patient->lmp->toDateString(),
        'edd' => $patient->edd->toDateString(),
    ]);

    $response->assertRedirect(route('patients.index'));

    $patient->refresh();

    // The legacy value survives untouched...
    expect($patient->address)->toBe('Santa Maria');
    // ...but display now prefers the structured address.
    expect($patient->formatted_address)->toBe("123 Mabini St., Green Village\nBarangay San Jose\nSan Jose del Monte");
});

// 13 & 14. Export/download completeness check works for both structured and
// legacy addresses.
it('does not flag a missing address on download when only the legacy address is present', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => 'Santa Maria',
        'philhealth_member' => 0,
    ]);
    MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), [
        'format' => 'csv',
    ]);

    $response->assertOk();
});

it('does not flag a missing address on download when only structured fields are present', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => null,
        'address_line' => '123 Mabini St.',
        'barangay' => 'San Jose',
        'city_municipality' => 'Santa Maria',
        'philhealth_member' => 0,
    ]);
    MedicalHistory::create(['patient_id' => $patient->id]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), [
        'format' => 'csv',
    ]);

    $response->assertOk();
});

it('flags a missing address on download when neither legacy nor structured address is present', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => null,
        'philhealth_member' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('patients.download', $patient->id), [
        'format' => 'csv',
    ]);

    $response->assertStatus(422);
    $response->assertJsonFragment(['missing' => ['Address']]);
});

// 15. Relevant print views still render.
it('renders the patient profile without errors for a structured address', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => null,
        'address_line' => '123 Mabini St.',
        'barangay' => 'San Jose',
        'city_municipality' => 'Santa Maria',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Barangay San Jose', false);
});

it('renders the patient profile without errors for a legacy-only address', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => 'Gubat Street Parada Santa Maria Bulacan',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Gubat Street Parada Santa Maria Bulacan', false);
});

it('renders the edit form showing the legacy address as a read-only reference', function () {
    $user = structuredAddressStaff();
    $patient = structuredAddressPatientFixture([
        'address' => 'Santa Maria',
    ]);

    $response = $this->actingAs($user)->get(route('patients.edit', $patient->id));

    $response->assertOk();
    $response->assertSee('Previous Address (Legacy)');
    $response->assertSee('Santa Maria');
});
