<?php

use App\Http\Controllers\PatientController;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function primaryArchiveStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function primaryArchivePatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Archive',
        'last_name' => 'Patient',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function primaryArchiveVisit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
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
    ], $overrides));
}

it('archives a visit with soft deletes and exposes it only on the archived visits page', function () {
    $user = primaryArchiveStaff();
    $patient = primaryArchivePatient();
    $visit = primaryArchiveVisit($patient);

    $this->actingAs($user)
        ->get(route('prenatal-visits.index'))
        ->assertOk()
        ->assertSee(route('prenatal-visits.archived'), false)
        ->assertSee(route('prenatal-visits.destroy', $visit->id), false)
        ->assertSee('title="Archive"', false)
        ->assertSee('This prenatal visit will be moved to Archived Prenatal Visits and can be restored later.');

    $this->actingAs($user)
        ->delete(route('prenatal-visits.destroy', $visit->id))
        ->assertRedirect(route('prenatal-visits.index'))
        ->assertSessionHas('success', 'Prenatal visit archived successfully.');

    $this->assertSoftDeleted('prenatal_visits', ['id' => $visit->id]);
    $this->assertDatabaseHas('prenatal_visits', [
        'id' => $visit->id,
        'patient_id' => $patient->id,
    ]);

    $this->actingAs($user)
        ->get(route('prenatal-visits.index'))
        ->assertOk()
        ->assertDontSee(route('prenatal-visits.edit', $visit->id), false);

    $this->actingAs($user)
        ->get(route('prenatal-visits.archived'))
        ->assertOk()
        ->assertSeeText('Archived Prenatal Visits')
        ->assertSeeText('Archive Patient')
        ->assertSeeText('Routine prenatal assessment')
        ->assertSeeText('120/80 mmHg')
        ->assertSeeText('60.5 kg')
        ->assertSeeText('24.5 weeks')
        ->assertSee(route('prenatal-visits.restore', $visit->id), false)
        ->assertDontSee('title="Delete"', false);
});

it('restores an individually archived visit without deleting it', function () {
    $user = primaryArchiveStaff();
    $patient = primaryArchivePatient();
    $visit = primaryArchiveVisit($patient);
    $visit->delete();

    $this->actingAs($user)
        ->post(route('prenatal-visits.restore', $visit->id))
        ->assertRedirect(route('prenatal-visits.index'))
        ->assertSessionHas('success', 'Prenatal visit restored successfully.');

    $this->assertDatabaseHas('prenatal_visits', [
        'id' => $visit->id,
        'patient_id' => $patient->id,
        'deleted_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('prenatal-visits.index'))
        ->assertOk()
        ->assertSee(route('patients.show', ['patient' => $patient->id, 'from' => 'prenatal-visits']), false);
});

it('excludes visits cascaded from archived patients and denies standalone restore', function () {
    $user = primaryArchiveStaff();
    $patient = primaryArchivePatient(['first_name' => 'Archived Parent', 'last_name' => 'Patient']);
    $visit = primaryArchiveVisit($patient);

    $this->actingAs($user)->delete(route('patients.destroy', $patient->id));

    $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    $this->assertSoftDeleted('prenatal_visits', ['id' => $visit->id]);

    $this->actingAs($user)
        ->get(route('prenatal-visits.archived'))
        ->assertOk()
        ->assertDontSeeText('Archived Parent Patient');

    $this->actingAs($user)
        ->post(route('prenatal-visits.restore', $visit->id))
        ->assertNotFound();

    $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    $this->assertSoftDeleted('prenatal_visits', ['id' => $visit->id]);
});

it('keeps the existing patient archive route and cascade behavior separate', function () {
    $user = primaryArchiveStaff();
    $patient = primaryArchivePatient();
    $visit = primaryArchiveVisit($patient);

    $this->actingAs($user)->delete(route('patients.destroy', $patient->id));

    $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    $this->assertSoftDeleted('prenatal_visits', ['id' => $visit->id]);

    $restoreRoute = app('router')->getRoutes()->getByName('patients.restore');
    expect($restoreRoute?->getActionName())
        ->toBe(PatientController::class . '@restore');
});

it('restricts archived visits and standalone restore to staff', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $patient = primaryArchivePatient();
    $visit = primaryArchiveVisit($patient);
    $visit->delete();

    $this->actingAs($admin)
        ->get(route('prenatal-visits.archived'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('prenatal-visits.restore', $visit->id))
        ->assertForbidden();
});
