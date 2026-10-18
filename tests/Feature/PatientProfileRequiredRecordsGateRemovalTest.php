<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| QA Fix - Patient Profile "Complete Required Records First" removal
|--------------------------------------------------------------------------
| The Patient Profile previously hid "Add Prenatal Visit" behind a
| $canAddPrenatalVisit gate (Medical History + Ultrasound + Birth Plan
| all present) and showed a disabled "Complete Required Records First"
| button with a hover checklist instead. That UI-only restriction was
| inconsistent with the server (PrenatalVisitController never checked
| record completeness) and with the ungated Prenatal Visits index page.
|
| This sprint removes the UI gate only:
| - "Add Prenatal Visit" always renders for eligible ONGOING non-admin
|   users.
| - The disabled button and hover checklist are gone entirely.
| - Authorization (non-admin) and status (ONGOING-only) rules are
|   unchanged.
| - The clinical layer is untouched: a visit saved before the three
|   records exist still classifies ASSESSMENT INCOMPLETE (never LOW).
*/

function gateRemovalStaff(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Gate Removal Staff']);
}

function gateRemovalAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Gate Removal Admin']);
}

function gateRemovalPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Gate',
        'last_name' => 'Removal',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

it('shows Add Prenatal Visit for an eligible ONGOING patient with no prerequisite records', function () {
    $staff = gateRemovalStaff();
    $patient = gateRemovalPatient();

    $this->actingAs($staff)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertSee('Add Prenatal Visit')
        ->assertSee(route('prenatal-visits.create', ['patient_id' => $patient->id]), false);
});

it('no longer renders the Complete Required Records First button or hover checklist', function () {
    $staff = gateRemovalStaff();
    $patient = gateRemovalPatient();

    $this->actingAs($staff)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertDontSee('Complete Required Records First')
        ->assertDontSee('Complete the following records before adding a prenatal visit');
});

it('keeps the admin exclusion: admins never see the Add Prenatal Visit action', function () {
    $admin = gateRemovalAdmin();
    $patient = gateRemovalPatient();

    $this->actingAs($admin)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertDontSee('Add Prenatal Visit')
        ->assertDontSee('Complete Required Records First');
});

it('does not show Add Prenatal Visit for a DELIVERED patient', function () {
    $staff = gateRemovalStaff();
    $patient = gateRemovalPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDay()->toDateString()]);

    $this->actingAs($staff)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertDontSee('Add Prenatal Visit')
        ->assertSee('View Pregnancy History');
});

it('still renders core profile sections after the gate removal', function () {
    $staff = gateRemovalStaff();
    $patient = gateRemovalPatient();

    $this->actingAs($staff)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertSee('Pregnancy History')
        ->assertSee('Latest Prenatal Visit')
        ->assertSee($patient->first_name);
});

it('allows creating a prenatal visit with no prerequisite records and classifies it ASSESSMENT INCOMPLETE', function () {
    $staff = gateRemovalStaff();
    $patient = gateRemovalPatient();

    $this->actingAs($staff)->post(route('prenatal-visits.store'), [
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'bp_sys' => '110',
        'bp_dia' => '70',
        'weight' => '60',
        'gestational_age' => '20',
        'hypertension' => '0',
        'diabetes' => '0',
        'anemia' => '0',
    ])->assertSessionDoesntHaveErrors(['patient_id']);

    $visit = PrenatalVisit::where('patient_id', $patient->id)->first();

    expect($visit)->not->toBeNull()
        ->and($visit->risk_level)->toBe('ASSESSMENT INCOMPLETE')
        ->and($visit->decision_source)->toBe('COMPLETENESS');
});
