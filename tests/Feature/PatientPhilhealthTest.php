<?php

use App\Models\Patient;
use App\Models\User;

it('clears the philhealth number when the patient is not a member', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('patients.store'), [
        'first_name' => 'Ana',
        'middle_name' => '',
        'last_name' => 'Dela Cruz',
        'birthdate' => '1995-01-01',
        'age' => 31,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => 'ana@example.com',
        'civil_status' => 'Single',
        'philhealth_member' => '0',
        'philhealth_number' => 'PH123456789',
        'gravida' => 2,
        'para' => 1,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => '2025-01-01',
        'edd' => '2025-10-08',
    ]);

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect($patient)->not->toBeNull();
    expect($patient->philhealth_member)->toBe(0);
    expect($patient->philhealth_number)->toBeNull();
});
