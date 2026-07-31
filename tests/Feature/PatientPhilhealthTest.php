<?php

use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // The patients table migration files are missing previous_cs/miscarriage
    // (documented pre-existing gap), so the test schema cannot persist valid
    // patient writes without them. Add the columns to the in-memory test schema
    // only, guarded so the tests keep working once real migrations add them.
    // No migration file is created and no php artisan migrate is run.
    if (!Schema::hasColumn('patients', 'previous_cs')) {
        Schema::table('patients', function ($table) {
            $table->boolean('previous_cs')->default(false);
        });
    }

    if (!Schema::hasColumn('patients', 'miscarriage')) {
        Schema::table('patients', function ($table) {
            $table->integer('miscarriage')->default(0);
        });
    }
});

function philhealthPayload(array $overrides = []): array
{
    return array_merge([
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
        'philhealth_number' => null,
        'gravida' => 2,
        'para' => 1,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => '2025-01-01',
        'edd' => '2025-10-08',
    ], $overrides);
}

function philhealthStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

it('stores a null philhealth number when a non-member submits a number', function () {
    $user = philhealthStaff();

    $response = $this->actingAs($user)->post(route('patients.store'), philhealthPayload([
        'philhealth_member' => '0',
        'philhealth_number' => 'PH123456789',
    ]));

    $response->assertRedirect(route('patients.index'));

    $patient = Patient::latest('id')->first();

    expect($patient)->not->toBeNull();
    expect($patient->philhealth_member)->toBe(0);
    expect($patient->philhealth_number)->toBeNull();
});

it('clears the philhealth number when an existing member is changed to a non-member', function () {
    $user = philhealthStaff();
    $patient = Patient::create(philhealthPayload([
        'philhealth_member' => 1,
        'philhealth_number' => 'PH123456789',
    ]));

    $response = $this->actingAs($user)->patch(route('patients.update', $patient->id), philhealthPayload([
        'philhealth_member' => '0',
        'philhealth_number' => 'PH123456789',
    ]));

    $response->assertRedirect(route('patients.index'));

    $patient->refresh();

    expect($patient->philhealth_member)->toBe(0);
    expect($patient->philhealth_number)->toBeNull();
});

it('preserves the philhealth number when the patient remains a member', function () {
    $user = philhealthStaff();
    $patient = Patient::create(philhealthPayload([
        'philhealth_member' => 1,
        'philhealth_number' => 'PH123456789',
    ]));

    $response = $this->actingAs($user)->patch(route('patients.update', $patient->id), philhealthPayload([
        'philhealth_member' => '1',
        'philhealth_number' => 'PH987654321',
    ]));

    $response->assertRedirect(route('patients.index'));

    $patient->refresh();

    expect($patient->philhealth_member)->toBe(1);
    expect($patient->philhealth_number)->toBe('PH987654321');
});

it('fails validation when a member is saved without a philhealth number', function () {
    $user = philhealthStaff();
    $patient = Patient::create(philhealthPayload([
        'philhealth_member' => 1,
        'philhealth_number' => 'PH123456789',
    ]));

    $response = $this->actingAs($user)->patch(route('patients.update', $patient->id), philhealthPayload([
        'philhealth_member' => '1',
        'philhealth_number' => null,
    ]));

    $response->assertSessionHasErrors('philhealth_number');

    $patient->refresh();

    expect($patient->philhealth_number)->toBe('PH123456789');
});
