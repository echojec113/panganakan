<?php

use App\Models\Patient;
use App\Models\User;

function profileAddressDisplayStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function profileAddressDisplayPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Jenny',
        'last_name' => 'Cruz',
        'birthdate' => today()->subYears(27)->toDateString(),
        'age' => 27,
        'contact_number' => '09171234567',
        'civil_status' => 'Married',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'status' => 'ONGOING',
        'lmp' => today()->subWeeks(10)->toDateString(),
        'edd' => today()->subWeeks(10)->addDays(280)->toDateString(),
    ], $overrides));
}

it('renders the structured address as one inline comma-separated line with a location icon on the profile header', function () {
    $user = profileAddressDisplayStaff();
    $patient = profileAddressDisplayPatient([
        'address' => null,
        'address_line' => 'Blk 8 Lot 5 Fabahay 2000',
        'barangay' => 'Muzon',
        'city_municipality' => 'SJDM',
    ]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Blk 8 Lot 5 Fabahay 2000, Barangay Muzon, SJDM', false);

    // The old multi-line <br> presentation must no longer appear for this
    // address in the profile header.
    $response->assertDontSee('Blk 8 Lot 5 Fabahay 2000<br', false);
});

it('still renders a legacy address correctly on the profile header', function () {
    $user = profileAddressDisplayStaff();
    $patient = profileAddressDisplayPatient(['address' => 'Santa Maria, Bulacan']);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Santa Maria, Bulacan', false);
});

it('omits the address icon/line entirely when the patient has no legacy or structured address', function () {
    $user = profileAddressDisplayStaff();
    $patient = profileAddressDisplayPatient(['address' => null]);

    $response = $this->actingAs($user)->get(route('patients.show', $patient->id));

    $response->assertOk();
    // The location-pin icon path only renders when there is an address to show.
    $response->assertDontSee('M15 10.5a3 3 0 11-6 0 3 3 0 016 0z', false);
});

it('keeps the multiline formatted_address presentation intact on print/export views', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = profileAddressDisplayPatient([
        'address' => null,
        'address_line' => 'Blk 8 Lot 5 Fabahay 2000',
        'barangay' => 'Muzon',
        'city_municipality' => 'SJDM',
    ]);

    $response = $this->actingAs($admin)->get(route('view-all-records.pregnancy.print', $patient->id));

    $response->assertOk();
    // print-pregnancy.blade.php must still use nl2br (multiline), unaffected
    // by the profile-header-only change.
    $response->assertSee('Blk 8 Lot 5 Fabahay 2000<br', false);
});
