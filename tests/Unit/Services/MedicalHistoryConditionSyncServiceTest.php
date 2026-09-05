<?php

use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Services\MedicalHistoryConditionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function syncPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Sync',
        'last_name' => 'Patient',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
    ], $overrides));
}

function syncHistory(int $patientId, array $overrides = []): MedicalHistory
{
    return MedicalHistory::create(array_merge([
        'patient_id' => $patientId,
        'diabetes' => 0,
        'anemia' => 0,
    ], $overrides));
}

function syncService(): MedicalHistoryConditionSyncService
{
    return new MedicalHistoryConditionSyncService;
}

test('a confirmed diabetes visit sets the history diabetes value when previously false', function () {
    $patient = syncPatient();
    syncHistory($patient->id);

    $result = syncService()->syncConfirmedVisitConditions($patient, true, false);

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->diabetes)->toBe(1);
    expect($history->anemia)->toBe(0);
    expect($result['changed'])->toBeTrue();
    expect($result['updated_fields'])->toBe(['diabetes']);
});

test('a confirmed anemia visit sets the history anemia value when previously false', function () {
    $patient = syncPatient();
    syncHistory($patient->id);

    syncService()->syncConfirmedVisitConditions($patient, false, true);

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->anemia)->toBe(1);
    expect($history->diabetes)->toBe(0);
});

test('a negative diabetes visit does not clear a true history diabetes value', function () {
    $patient = syncPatient();
    syncHistory($patient->id, ['diabetes' => 1]);

    $result = syncService()->syncConfirmedVisitConditions($patient, false, false);

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->diabetes)->toBe(1);
    expect($result['changed'])->toBeFalse();
    expect($result['updated_fields'])->toBe([]);
});

test('a negative anemia visit does not clear a true history anemia value', function () {
    $patient = syncPatient();
    syncHistory($patient->id, ['anemia' => 1]);

    syncService()->syncConfirmedVisitConditions($patient, false, false);

    $history = MedicalHistory::where('patient_id', $patient->id)->first();

    expect($history->anemia)->toBe(1);
});

test('skips silently when no active medical history exists and reports the skipped reason', function () {
    $patient = syncPatient();

    $result = syncService()->syncConfirmedVisitConditions($patient, true, true);

    expect($result['changed'])->toBeFalse();
    expect($result['updated_fields'])->toBe([]);
    expect($result['skipped_reason'])->toBe('NO_ACTIVE_MEDICAL_HISTORY');
});

test('reports changed false and no updated fields when no value changes', function () {
    $patient = syncPatient();
    syncHistory($patient->id, ['diabetes' => 1, 'anemia' => 1]);

    $result = syncService()->syncConfirmedVisitConditions($patient, true, true);

    expect($result['changed'])->toBeFalse();
    expect($result['updated_fields'])->toBe([]);
    expect($result['skipped_reason'])->toBeNull();
});

test('reports changed true and the updated fields when a value changes', function () {
    $patient = syncPatient();
    syncHistory($patient->id, ['diabetes' => 0, 'anemia' => 1]);

    $result = syncService()->syncConfirmedVisitConditions($patient, true, true);

    expect($result['changed'])->toBeTrue();
    expect($result['updated_fields'])->toBe(['diabetes']);
    expect($result['skipped_reason'])->toBeNull();
});

test('never syncs the hypertension checkbox from a visit', function () {
    $patient = syncPatient();
    $history = syncHistory($patient->id, ['hypertension' => 0]);

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 110,
        'bp_dia' => 70,
        'weight' => 60,
        'gestational_age' => 20,
        'hypertension' => 1,
        'diabetes' => 1,
        'anemia' => 0,
    ]);

    syncService()->syncConfirmedVisitConditions($patient, (bool) $visit->diabetes, (bool) $visit->anemia, $visit);

    $history->refresh();

    expect($history->diabetes)->toBe(1);
    expect($history->hypertension)->toBe(0);
});

test('does not trigger any assessment or visit change', function () {
    $patient = syncPatient();
    syncHistory($patient->id);

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
        'risk_level' => 'HIGH',
    ]);

    syncService()->syncConfirmedVisitConditions($patient, true, false, $visit);

    $visit->refresh();

    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->assessment)->toBeNull();
    expect(PrenatalVisit::where('patient_id', $patient->id)->count())->toBe(1);
});

test('returns the visit id in the metadata when a visit is provided', function () {
    $patient = syncPatient();
    syncHistory($patient->id);

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

    $result = syncService()->syncConfirmedVisitConditions($patient, true, false, $visit);

    expect($result['visit_id'])->toBe($visit->id);
});

test('a delivered patient with diabetes Yes leaves the Medical History unchanged', function () {
    $patient = syncPatient(['status' => 'DELIVERED']);
    $history = syncHistory($patient->id, ['diabetes' => 0, 'anemia' => 0]);

    $result = syncService()->syncConfirmedVisitConditions($patient, true, false);

    $history->refresh();

    expect($history->diabetes)->toBe(0);
    expect($history->anemia)->toBe(0);
    expect($result['changed'])->toBeFalse();
    expect($result['updated_fields'])->toBe([]);
});

test('reports skipped_reason PATIENT_DELIVERED for a delivered patient', function () {
    $patient = syncPatient(['status' => 'DELIVERED']);
    syncHistory($patient->id);

    $result = syncService()->syncConfirmedVisitConditions($patient, true, true);

    expect($result['skipped_reason'])->toBe('PATIENT_DELIVERED');
});

test('does not write a sync audit entry for a delivered patient', function () {
    $patient = syncPatient(['status' => 'DELIVERED']);
    syncHistory($patient->id);

    syncService()->syncConfirmedVisitConditions($patient, true, false);

    expect(\App\Models\AuditLog::where('action', 'MEDICAL_HISTORY_SYNC')->count())->toBe(0);
});
