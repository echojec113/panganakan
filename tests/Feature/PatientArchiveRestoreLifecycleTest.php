<?php

use App\Models\Baby;
use App\Models\BirthPlan;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Patient Archive / Restore Lifecycle (DEPLA)
|--------------------------------------------------------------------------
| Covers: the former HTTP 500 on restore (Baby lacked SoftDeletes so
| Patient's restoring hook called an undefined withTrashed()), baby soft
| delete instead of hard delete, transaction rollback on mid-cascade
| failure, list ordering (active newest-first, archived most-recently-
| archived first), search/pagination ordering, authorization, and
| no-side-effects guarantees. SQLite :memory: only.
*/

function archiveRestoreLifecycleStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function archiveRestoreLifecyclePatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Archive',
        'last_name' => 'Restore',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09170000001',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

/**
 * Builds the full related-record set for one patient so archive/restore
 * cascades can be observed across every table in the lifecycle.
 *
 * @return array{visit: PrenatalVisit, ultrasound: Ultrasound, birthPlan: BirthPlan, medicalHistory: MedicalHistory, babyOne: Baby, babyTwo: Baby}
 */
function archiveRestoreLifecycleRelatedRecords(Patient $patient): array
{
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => today()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60.5,
        'gestational_age' => 24.5,
        'risk_level' => 'LOW',
        'assessment' => 'Routine prenatal assessment',
        'recommendation' => 'Continue routine care',
        'next_visit_date' => today()->addDays(30)->toDateString(),
    ]);

    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
    ]);

    $birthPlan = BirthPlan::create(['patient_id' => $patient->id]);
    $medicalHistory = MedicalHistory::create(['patient_id' => $patient->id]);

    $babyOne = Baby::create([
        'patient_id' => $patient->id,
        'date_of_birth' => '2026-05-01',
        'time_of_birth' => '08:00',
    ]);

    $babyTwo = Baby::create([
        'patient_id' => $patient->id,
        'date_of_birth' => '2026-05-01',
        'time_of_birth' => '08:01',
    ]);

    return compact('visit', 'ultrasound', 'birthPlan', 'medicalHistory', 'babyOne', 'babyTwo');
}

it('archives a patient through the authorized staff route', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();

    $this->actingAs($staff)
        ->delete(route('patients.destroy', $patient->id))
        ->assertRedirect(route('patients.index'));

    $this->assertSoftDeleted('patients', ['id' => $patient->id]);
});

it('restores an archived patient without HTTP 500', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    archiveRestoreLifecycleRelatedRecords($patient);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
    $this->assertSoftDeleted('patients', ['id' => $patient->id]);

    $this->actingAs($staff)
        ->post(route('patients.restore', $patient->id))
        ->assertRedirect(route('patients.index'))
        ->assertSessionHas('success', 'Patient restored successfully');

    $this->assertDatabaseHas('patients', [
        'id' => $patient->id,
        'deleted_at' => null,
    ]);
});

it('soft-deletes baby records during patient archival instead of hard-deleting them', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));

    $this->assertSoftDeleted('babies', ['id' => $records['babyOne']->id]);
    $this->assertSoftDeleted('babies', ['id' => $records['babyTwo']->id]);
    $this->assertDatabaseCount('babies', 2);
});

it('restores baby records together with the patient', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
    $this->assertSoftDeleted('babies', ['id' => $records['babyOne']->id]);

    $this->actingAs($staff)->post(route('patients.restore', $patient->id));

    $this->assertDatabaseHas('babies', ['id' => $records['babyOne']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('babies', ['id' => $records['babyTwo']->id, 'deleted_at' => null]);
});

it('archives and restores prenatal visits with the patient', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
    $this->assertSoftDeleted('prenatal_visits', ['id' => $records['visit']->id]);

    $this->actingAs($staff)->post(route('patients.restore', $patient->id));
    $this->assertDatabaseHas('prenatal_visits', [
        'id' => $records['visit']->id,
        'deleted_at' => null,
    ]);
});

it('archives and restores ultrasounds with the patient', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
    $this->assertSoftDeleted('ultrasounds', ['id' => $records['ultrasound']->id]);

    $this->actingAs($staff)->post(route('patients.restore', $patient->id));
    $this->assertDatabaseHas('ultrasounds', [
        'id' => $records['ultrasound']->id,
        'deleted_at' => null,
    ]);
});

it('keeps birth plan and medical history consistent across archive and restore', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
    $this->assertSoftDeleted('birth_plans', ['id' => $records['birthPlan']->id]);
    $this->assertSoftDeleted('medical_histories', ['id' => $records['medicalHistory']->id]);

    $this->actingAs($staff)->post(route('patients.restore', $patient->id));
    $this->assertDatabaseHas('birth_plans', ['id' => $records['birthPlan']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('medical_histories', ['id' => $records['medicalHistory']->id, 'deleted_at' => null]);
});

it('rolls back the entire archive when a cascade step fails', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    Patient::deleting(function (): void {
        throw new RuntimeException('simulated cascade failure');
    });

    $this->actingAs($staff)
        ->delete(route('patients.destroy', $patient->id))
        ->assertStatus(500);

    $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('prenatal_visits', ['id' => $records['visit']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('ultrasounds', ['id' => $records['ultrasound']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('babies', ['id' => $records['babyOne']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('birth_plans', ['id' => $records['birthPlan']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('medical_histories', ['id' => $records['medicalHistory']->id, 'deleted_at' => null]);
});

it('rolls back the entire restore when a cascade step fails', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
    $this->assertSoftDeleted('prenatal_visits', ['id' => $records['visit']->id]);

    Patient::restoring(function (): void {
        throw new RuntimeException('simulated cascade failure');
    });

    $this->actingAs($staff)
        ->post(route('patients.restore', $patient->id))
        ->assertStatus(500);

    // The cascade already un-deleted some rows before the failure; the
    // transaction must put them back into the archived state.
    $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    $this->assertSoftDeleted('prenatal_visits', ['id' => $records['visit']->id]);
    $this->assertSoftDeleted('ultrasounds', ['id' => $records['ultrasound']->id]);
    $this->assertSoftDeleted('babies', ['id' => $records['babyOne']->id]);
    $this->assertSoftDeleted('babies', ['id' => $records['babyTwo']->id]);
    $this->assertSoftDeleted('birth_plans', ['id' => $records['birthPlan']->id]);
    $this->assertSoftDeleted('medical_histories', ['id' => $records['medicalHistory']->id]);
});

it('creates no duplicate related records across archive and restore cycles', function () {
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    archiveRestoreLifecycleRelatedRecords($patient);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
        $this->actingAs($staff)->post(route('patients.restore', $patient->id));
    }

    $this->assertDatabaseCount('patients', 1);
    $this->assertDatabaseCount('prenatal_visits', 1);
    $this->assertDatabaseCount('ultrasounds', 1);
    $this->assertDatabaseCount('birth_plans', 1);
    $this->assertDatabaseCount('medical_histories', 1);
    $this->assertDatabaseCount('babies', 2);
});

it('follows the documented lifecycle for independently archived related records', function () {
    /*
     * DOCUMENTED LIMITATION: the schema has no column distinguishing a
     * record archived on its own from one archived by the patient cascade
     * (timestamps are second-precision and unreliable as a provenance
     * marker). Patient restore therefore restores EVERY related record in
     * the trash, including ones archived independently beforehand. This
     * test pins the current, documented behavior; changing it requires an
     * approved schema/lifecycle redesign.
     */
    $staff = archiveRestoreLifecycleStaff();
    $patient = archiveRestoreLifecyclePatient();
    $records = archiveRestoreLifecycleRelatedRecords($patient);

    $records['visit']->delete();
    $records['ultrasound']->delete();
    $this->assertSoftDeleted('prenatal_visits', ['id' => $records['visit']->id]);

    $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));

    // Still archived after the patient cascade touches its own records.
    $this->assertSoftDeleted('prenatal_visits', ['id' => $records['visit']->id]);
    $this->assertSoftDeleted('ultrasounds', ['id' => $records['ultrasound']->id]);

    $this->actingAs($staff)->post(route('patients.restore', $patient->id));

    // Documented behavior: patient restore lifts related rows too.
    $this->assertDatabaseHas('prenatal_visits', ['id' => $records['visit']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('ultrasounds', ['id' => $records['ultrasound']->id, 'deleted_at' => null]);
});

it('rejects unauthorized restore attempts without modifying records', function () {
    $patient = archiveRestoreLifecyclePatient();
    archiveRestoreLifecycleRelatedRecords($patient);

    // Guest first, before actingAs() pins an authenticated guard.
    $this->post(route('patients.restore', $patient->id))->assertRedirect(route('login'));

    $patientUser = User::factory()->create(['role' => 'patient']);
    $this->actingAs($patientUser)
        ->post(route('patients.restore', $patient->id))
        ->assertForbidden();
    $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);

    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)
        ->post(route('patients.restore', $patient->id))
        ->assertForbidden();
    $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);

    $staff = archiveRestoreLifecycleStaff();
    $this->actingAs($staff)
        ->post(route('patients.restore', 999999))
        ->assertNotFound();
});

it('lists active patients newest registration first', function () {
    $staff = archiveRestoreLifecycleStaff();

    $first = archiveRestoreLifecyclePatient(['first_name' => 'Newest', 'last_name' => 'Registered']);
    $second = archiveRestoreLifecyclePatient(['first_name' => 'Middle', 'last_name' => 'Registered']);
    $third = archiveRestoreLifecyclePatient(['first_name' => 'Oldest', 'last_name' => 'Registered']);

    // Registration timestamps deliberately disagree with insert order so a
    // naive id-ordered listing would fail this assertion.
    $first->forceFill(['created_at' => now()->subDays(2)])->save();
    $second->forceFill(['created_at' => now()->subDays(10)])->save();
    $third->forceFill(['created_at' => now()->subDays(6)])->save();

    $response = $this->actingAs($staff)->get(route('patients.index'));
    $response->assertOk();

    expect($response->viewData('patients')->pluck('id')->all())
        ->toBe([$first->id, $third->id, $second->id]);
});

it('lists archived patients most recently archived first', function () {
    $staff = archiveRestoreLifecycleStaff();

    $first = archiveRestoreLifecyclePatient(['first_name' => 'Recent', 'last_name' => 'Archive']);
    $second = archiveRestoreLifecyclePatient(['first_name' => 'Oldest', 'last_name' => 'Archive']);
    $third = archiveRestoreLifecyclePatient(['first_name' => 'Middle', 'last_name' => 'Archive']);

    foreach ([$first, $second, $third] as $patient) {
        $this->actingAs($staff)->delete(route('patients.destroy', $patient->id));
    }

    // Archive timestamps deliberately disagree with delete order so an
    // id-ordered listing would fail this assertion.
    $first->forceFill(['deleted_at' => now()->subMinutes(5)])->save();
    $second->forceFill(['deleted_at' => now()->subHours(5)])->save();
    $third->forceFill(['deleted_at' => now()->subHour()])->save();

    $response = $this->actingAs($staff)->get(route('patients.trashed'));
    $response->assertOk();

    expect($response->viewData('patients')->pluck('id')->all())
        ->toBe([$first->id, $third->id, $second->id]);
});

it('preserves newest-first ordering through search and pagination', function () {
    $staff = archiveRestoreLifecycleStaff();

    $cohort = collect(range(1, 12))->map(function (int $i) use ($staff) {
        $patient = archiveRestoreLifecyclePatient([
            'first_name' => 'Paginated',
            'last_name' => "Order{$i}",
            'contact_number' => '091700000' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        ]);
        $patient->forceFill(['created_at' => now()->subDays(30)->addMinutes($i)])->save();

        return $patient;
    });

    $newestFirstIds = $cohort->reverse()->pluck('id')->values()->all();

    $pageOne = $this->actingAs($staff)->get(route('patients.index', ['page' => 1]));
    $pageOne->assertOk();
    expect($pageOne->viewData('patients')->pluck('id')->all())
        ->toBe(array_slice($newestFirstIds, 0, 10));

    $pageTwo = $this->actingAs($staff)->get(route('patients.index', ['page' => 2]));
    $pageTwo->assertOk();
    expect($pageTwo->viewData('patients')->pluck('id')->all())
        ->toBe(array_slice($newestFirstIds, 10, 2));

    $searchPageOne = $this->actingAs($staff)->get(route('patients.index', [
        'search' => 'Order',
        'page' => 1,
    ]));
    $searchPageOne->assertOk();
    expect($searchPageOne->viewData('patients')->pluck('id')->all())
        ->toBe(array_slice($newestFirstIds, 0, 10));

    $searchPageTwo = $this->actingAs($staff)->get(route('patients.index', [
        'search' => 'Order',
        'page' => 2,
    ]));
    $searchPageTwo->assertOk();
    expect($searchPageTwo->viewData('patients')->pluck('id')->all())
        ->toBe(array_slice($newestFirstIds, 10, 2));
});

it('leaves unrelated patients and their records untouched by archive and restore', function () {
    $staff = archiveRestoreLifecycleStaff();

    $archived = archiveRestoreLifecyclePatient(['first_name' => 'Target', 'last_name' => 'Patient']);
    $archivedRecords = archiveRestoreLifecycleRelatedRecords($archived);

    $untouched = archiveRestoreLifecyclePatient([
        'first_name' => 'Untouched',
        'last_name' => 'Patient',
        'contact_number' => '09170000002',
    ]);
    $untouchedRecords = archiveRestoreLifecycleRelatedRecords($untouched);

    $this->actingAs($staff)->delete(route('patients.destroy', $archived->id));
    $this->actingAs($staff)->post(route('patients.restore', $archived->id));

    $this->assertDatabaseHas('patients', ['id' => $untouched->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('prenatal_visits', ['id' => $untouchedRecords['visit']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('ultrasounds', ['id' => $untouchedRecords['ultrasound']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('babies', ['id' => $untouchedRecords['babyOne']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('birth_plans', ['id' => $untouchedRecords['birthPlan']->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('medical_histories', ['id' => $untouchedRecords['medicalHistory']->id, 'deleted_at' => null]);

    $this->assertDatabaseHas('patients', ['id' => $archived->id, 'deleted_at' => null]);
    $this->assertDatabaseHas('babies', ['id' => $archivedRecords['babyOne']->id, 'deleted_at' => null]);
});
