<?php

use App\Models\AuditLog;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function pvStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function pvPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Pre',
        'last_name' => 'Visit',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function pvHistory(int $patientId, array $overrides = []): MedicalHistory
{
    return MedicalHistory::create(array_merge([
        'patient_id' => $patientId,
        'diabetes' => 0,
        'anemia' => 0,
    ], $overrides));
}

function pvPayload(array $overrides = []): array
{
    return array_merge([
        'patient_id' => null,
        'visit_date' => now()->toDateString(),
        'bp_sys' => '110',
        'bp_dia' => '70',
        'weight' => '60',
        'gestational_age' => '20',
        'hypertension' => '0',
        'diabetes' => '0',
        'anemia' => '0',
    ], $overrides);
}

test('storing a prenatal visit with diabetes Yes updates the existing Medical History diabetes to true', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id);

    $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload(['patient_id' => $patient->id, 'diabetes' => '1']));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->diabetes)->toBe(1);
    expect($history->anemia)->toBe(0);
});

test('storing a prenatal visit with anemia Yes updates the existing Medical History anemia to true', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id);

    $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload(['patient_id' => $patient->id, 'anemia' => '1']));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->anemia)->toBe(1);
    expect($history->diabetes)->toBe(0);
});

test('storing a prenatal visit with diabetes No does not clear the Medical History diabetes value', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id, ['diabetes' => 1]);

    $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload(['patient_id' => $patient->id, 'diabetes' => '0']));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->diabetes)->toBe(1);
});

test('storing a prenatal visit with anemia No does not clear the Medical History anemia value', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id, ['anemia' => 1]);

    $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload(['patient_id' => $patient->id, 'anemia' => '0']));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->anemia)->toBe(1);
});

test('stores a prenatal visit with confirmed diabetes and writes a MEDICAL_HISTORY_SYNC audit entry', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id);

    $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload(['patient_id' => $patient->id, 'diabetes' => '1']));

    $audit = AuditLog::where('action', 'MEDICAL_HISTORY_SYNC')
        ->where('module', 'MEDICAL_HISTORY')
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->description)->toContain('diabetes');
    expect($audit->description)->toContain('updated from prenatal visit ID');
});

test('does not write a sync audit entry when the Medical History is missing', function () {
    $user = pvStaff();
    $patient = pvPatient();

    $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload(['patient_id' => $patient->id, 'diabetes' => '1']));

    expect(AuditLog::where('action', 'MEDICAL_HISTORY_SYNC')->count())->toBe(0);
});

test('does not write a sync audit entry when no value changes', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id, ['diabetes' => 1, 'anemia' => 1]);

    $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload(['patient_id' => $patient->id, 'diabetes' => '1', 'anemia' => '1']));

    expect(AuditLog::where('action', 'MEDICAL_HISTORY_SYNC')->count())->toBe(0);
});

test('updating a prenatal visit to diabetes Yes syncs the Medical History value', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id);

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 110,
        'bp_dia' => 70,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 0,
        'anemia' => 0,
    ]);

    $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), pvPayload([
        'patient_id' => $patient->id,
        'diabetes' => '1',
    ]));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->diabetes)->toBe(1);
});

test('updating a prenatal visit from diabetes Yes to No does not clear the Medical History value', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id, ['diabetes' => 1]);

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 110,
        'bp_dia' => 70,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 0,
        'diabetes' => 1,
        'anemia' => 0,
    ]);

    $this->actingAs($user)->put(route('prenatal-visits.update', $visit->id), pvPayload([
        'patient_id' => $patient->id,
        'diabetes' => '0',
    ]));

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->diabetes)->toBe(1);
});

test('does not sync when the prenatal visit cannot be stored', function () {
    $user = pvStaff();
    $patient = pvPatient();
    pvHistory($patient->id);

    $response = $this->actingAs($user)->post(route('prenatal-visits.store'), pvPayload([
        'patient_id' => $patient->id,
        'diabetes' => '1',
        'bp_sys' => null,
    ]));

    $response->assertSessionHasErrors('bp_sys');

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->diabetes)->toBe(0);
    expect(AuditLog::where('action', 'MEDICAL_HISTORY_SYNC')->count())->toBe(0);
});
