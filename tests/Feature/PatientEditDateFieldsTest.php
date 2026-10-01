<?php

use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;

function patientEditDateFixture(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Ana',
        'last_name' => 'Cruz',
        'birthdate' => today()->subYears(26)->toDateString(),
        'age' => 26,
        'address' => 'Test address',
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

it('renders saved patient date fields in the HTML date input format', function () {
    $patient = patientEditDateFixture();
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $response->assertOk();

    expect($response->getContent())
        ->toMatch('/<input(?=[^>]*id="birthdate")(?=[^>]*value="' . $patient->birthdate->format('Y-m-d') . '")[^>]*>/s')
        ->toMatch('/<input(?=[^>]*id="lmp")(?=[^>]*value="' . $patient->lmp->format('Y-m-d') . '")[^>]*>/s')
        ->toMatch('/<input(?=[^>]*id="edd")(?=[^>]*value="' . $patient->edd->format('Y-m-d') . '")[^>]*>/s');
});

it('renders null patient date fields as empty values', function () {
    $patient = patientEditDateFixture([
        'birthdate' => null,
        'lmp' => null,
        'edd' => null,
    ]);
    $staff = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($staff)->get(route('patients.edit', $patient));

    $response->assertOk();

    expect($response->getContent())
        ->toMatch('/<input(?=[^>]*id="birthdate")(?=[^>]*value="")[^>]*>/s')
        ->toMatch('/<input(?=[^>]*id="lmp")(?=[^>]*value="")[^>]*>/s')
        ->toMatch('/<input(?=[^>]*id="edd")(?=[^>]*value="")[^>]*>/s');
});

it('keeps submitted date values ahead of saved values after validation fails', function () {
    $patient = patientEditDateFixture();
    $staff = User::factory()->create(['role' => 'staff']);
    $birthdate = '2000-05-10';
    $lmp = today()->subWeeks(18)->toDateString();
    $edd = Carbon::parse($lmp)->addDays(280)->toDateString();

    $update = $this->actingAs($staff)->from(route('patients.edit', $patient))
        ->patch(route('patients.update', $patient), [
            'first_name' => '',
            'last_name' => $patient->last_name,
            'birthdate' => $birthdate,
            'age' => 26,
            'address' => $patient->address,
            'contact_number' => $patient->contact_number,
            'civil_status' => $patient->civil_status,
            'philhealth_member' => '0',
            'philhealth_number' => '',
            'gravida' => 1,
            'para' => 0,
            'previous_cs' => 0,
            'miscarriage' => 0,
            'lmp' => $lmp,
            'edd' => $edd,
        ]);

    $update->assertSessionHasErrors('first_name');

    $editPage = $this->get(route('patients.edit', $patient));

    $editPage->assertOk();

    expect($editPage->getContent())
        ->toMatch('/<input(?=[^>]*id="birthdate")(?=[^>]*value="' . $birthdate . '")[^>]*>/s')
        ->toMatch('/<input(?=[^>]*id="lmp")(?=[^>]*value="' . $lmp . '")[^>]*>/s')
        ->toMatch('/<input(?=[^>]*id="edd")(?=[^>]*value="' . $edd . '")[^>]*>/s');
});
