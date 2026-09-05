<?php

use App\Models\Patient;
use App\Models\MedicalHistory;
use App\Models\Ultrasound;
use App\Models\BirthPlan;
use App\Services\CompletenessValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('no required records returns all three missing labels', function () {
    $patient = Patient::create([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
    ]);

    $validator = new CompletenessValidator;

    $missing = $validator->missingRequiredRecords($patient);

    expect($missing)->toBe(['Medical History', 'Ultrasound Record', 'Birth Plan']);
});

test('only medical history exists returns ultrasound record and birth plan', function () {
    $patient = Patient::create([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
    ]);

    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false]);

    $validator = new CompletenessValidator;

    $missing = $validator->missingRequiredRecords($patient);

    expect($missing)->toBe(['Ultrasound Record', 'Birth Plan']);
});

test('medical history and ultrasound exist returns birth plan', function () {
    $patient = Patient::create([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
    ]);

    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false]);
    Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->toDateString()]);

    $validator = new CompletenessValidator;

    $missing = $validator->missingRequiredRecords($patient);

    expect($missing)->toBe(['Birth Plan']);
});

test('all required records exist returns empty array', function () {
    $patient = Patient::create([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
    ]);

    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false]);
    Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->toDateString()]);
    BirthPlan::create(['patient_id' => $patient->id]);

    $validator = new CompletenessValidator;

    $missing = $validator->missingRequiredRecords($patient);

    expect($missing)->toBe([]);
});

test('isComplete returns true when all three records exist', function () {
    $patient = Patient::create([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
    ]);

    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false]);
    Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => now()->toDateString()]);
    BirthPlan::create(['patient_id' => $patient->id]);

    $validator = new CompletenessValidator;

    expect($validator->isComplete($patient))->toBeTrue();
});

test('isComplete returns false when any record is missing', function () {
    $patient = Patient::create([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 25,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
    ]);

    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => false]);
    // No Ultrasound, no BirthPlan

    $validator = new CompletenessValidator;

    expect($validator->isComplete($patient))->toBeFalse();
});
