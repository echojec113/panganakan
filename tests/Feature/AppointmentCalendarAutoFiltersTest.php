<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| QA FIX 3 - Appointments calendar filters auto-apply on change
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). The GET filter form keeps
| exactly the same server-side query-parameter filtering (baseQuery in
| AppointmentCalendarController); only the explicit Apply Filters button is
| gone. Each select now submits the form on change through requestSubmit(),
| the hidden view/date inputs survive the round-trip, combined filters keep
| working, and period navigation still carries the active filters.
*/

function qaFilterAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Filter Admin']);
}

function qaFilterPatient(string $first, string $last): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => "qafilter{$sequence}@clinic.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function qaFilterAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
        'status' => Appointment::STATUS_PENDING,
    ], $overrides));
}

it('renders the filter form without an Apply button and wired to submit on change', function () {
    $html = $this->actingAs(qaFilterAdmin())
        ->get(route('appointments.index'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->not->toContain('Apply Filters')
        ->toContain('id="appointmentFilterForm"')
        // Both selects auto-submit; the submit-event guard survives too.
        ->toContain('onchange="document.getElementById(\'appointmentFilterForm\').requestSubmit()"')
        ->toContain('data-busy')
        ->toContain('filterForm.removeAttribute(\'data-busy\')')
        // The hidden calendar anchors that must survive every filter submit.
        ->toContain('name="view"')
        ->toContain('name="date"');

    expect(substr_count($html, 'onchange="document.getElementById(\'appointmentFilterForm\').requestSubmit()"'))
        ->toBe(2);
});

it('applies a combined service-type and status filter through the existing query string', function () {
    $admin = qaFilterAdmin();
    qaFilterAppointment(qaFilterPatient('Alpha', 'Filterable'), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'status' => Appointment::STATUS_CONFIRMED,
    ]);
    qaFilterAppointment(qaFilterPatient('Beta', 'Nonmatching'), [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'status' => Appointment::STATUS_PENDING,
    ]);

    $this->actingAs($admin)
        ->get(route('appointments.index', [
            'view' => 'day',
            'date' => '2026-11-05',
            'type' => Appointment::TYPE_CHECKUP,
            'status' => Appointment::STATUS_CONFIRMED,
        ]))
        ->assertOk()
        ->assertSee('Alpha Filterable')
        ->assertDontSee('Beta Nonmatching');

    $this->actingAs($admin)
        ->get(route('appointments.index', [
            'view' => 'day',
            'date' => '2026-11-05',
            'type' => Appointment::TYPE_COUNSELING,
            'status' => Appointment::STATUS_PENDING,
        ]))
        ->assertOk()
        ->assertSee('Beta Nonmatching')
        ->assertDontSee('Alpha Filterable');
});

it('keeps the active filters on the selects and the period navigation links', function () {
    $admin = qaFilterAdmin();

    $html = $this->actingAs($admin)
        ->get(route('appointments.index', [
            'view' => 'day',
            'date' => '2026-11-05',
            'type' => Appointment::TYPE_COUNSELING,
            'status' => Appointment::STATUS_PENDING,
        ]))
        ->assertOk()
        ->getContent();

    expect($html)
        // Selected values stay selected after the round-trip...
        ->toContain('<option value="counseling" selected>')
        ->toContain('<option value="pending" selected>')
        // ...and Prev/Today/Next/month navigation re-merges both filters.
        ->toContain('type=counseling')
        ->toContain('status=pending');
});
