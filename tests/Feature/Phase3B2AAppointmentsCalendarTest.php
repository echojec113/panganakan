<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Phase 3B.2A - Appointments Calendar (Admin + Staff, READ-ONLY)
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers clinic-role access,
| Month/Year/Day rendering, the effective display-date rule, type/status
| filtering, safe fallbacks for invalid parameters, date-only (no invented
| clock time) handling, the embedded read-only details panel (including
| soft-deleted patient degradation) and the no-write guarantees.
*/

function phase3b2aAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Calendar Admin']);
}

function phase3b2aStaff(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Calendar Staff']);
}

function phase3b2aPatientUser(): User
{
    return User::factory()->create(['role' => 'patient', 'name' => 'Portal Patient']);
}

function phase3b2aPatient(string $first = 'Amara', string $last = 'Dela Cruz', string $contact = '09171234567'): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => $contact,
        'email' => "phase3b2a{$sequence}@clinic.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase3b2aAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| 1-4. Clinic-role access boundary
|--------------------------------------------------------------------------
*/

it('lets an admin view the appointments calendar', function () {
    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('Appointments');
});

it('lets staff view the appointments calendar', function () {
    $this->actingAs(phase3b2aStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('Appointments');
});

it('redirects guests to the login page', function () {
    $this->get(route('appointments.index'))
        ->assertRedirect(route('login'));
});

it('forbids patient accounts from the calendar', function () {
    $this->actingAs(phase3b2aPatientUser())
        ->get(route('appointments.index'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 5-6. Page shell + month grid
|--------------------------------------------------------------------------
*/

it('renders the month grid, view switcher, filters and sidebar entry', function () {
    phase3b2aAppointment(phase3b2aPatient());

    $response = $this->actingAs(phase3b2aAdmin())->get(route('appointments.index'));

    $response->assertOk()
        ->assertSee('Sun')
        ->assertSee('Sat')
        ->assertSee('Month')
        ->assertSee('Year')
        ->assertSee('Day')
        ->assertSee('Service Type')
        ->assertSee('Status')
        ->assertSee('Appointments', false); // sidebar link href=route(...)

    // Sidebar entry must also be present on the dashboard for both roles.
    $this->actingAs(phase3b2aStaff())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('appointments.index'), false);
});

it('shows the calendar month label for the anchored date', function () {
    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15']))
        ->assertOk()
        ->assertSee('November 2026');
});

/*
|--------------------------------------------------------------------------
| 7-9. Effective display-date rule
|--------------------------------------------------------------------------
*/

it('places pending appointments on the requested date', function () {
    phase3b2aAppointment(phase3b2aPatient('Pendinga', 'Requestedonly'), [
        'status' => Appointment::STATUS_PENDING,
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertSee('Requestedonly');
});

it('places confirmed appointments on the confirmed date, not the requested date', function () {
    phase3b2aAppointment(phase3b2aPatient('Confirmeda', 'Moveddate'), [
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_date' => '2026-12-20',
        'confirmed_time' => '14:00',
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertDontSee('Moveddate');

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-12-20']))
        ->assertOk()
        ->assertSee('Moveddate');
});

it('falls back to the requested date for a confirmed appointment without a confirmed date', function () {
    $appointment = phase3b2aAppointment(phase3b2aPatient('Fallbacka', 'Nodateconfirmed'));
    // forceFill->save: Phase 3D.4B narrowed $fillable and status is a
    // server-owned column mass assignment ignores.
    $appointment->forceFill(['status' => Appointment::STATUS_CONFIRMED])->save();

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertSee('Nodateconfirmed');
});

/*
|--------------------------------------------------------------------------
| 10-13. Filtering + safe parameter fallbacks
|--------------------------------------------------------------------------
*/

it('shows cancelled appointments by default and still honors an explicit status filter', function () {
    phase3b2aAppointment(phase3b2aPatient('Cancelleda', 'Historyrow'), [
        'status' => Appointment::STATUS_CANCELLED,
    ]);
    phase3b2aAppointment(phase3b2aPatient('Pendinga', 'Pendingrow'), [
        'status' => Appointment::STATUS_PENDING,
    ]);

    // No status query parameter: All Statuses is the default, so both the
    // cancelled history row and the pending row are visible.
    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15']))
        ->assertOk()
        ->assertSee('Historyrow')
        ->assertSee('Pendingrow');

    // An explicit specific status is applied normally and narrows the list.
    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'status' => 'pending']))
        ->assertOk()
        ->assertSee('Pendingrow')
        ->assertDontSee('Historyrow');

    // An explicit active filter still hides cancelled history.
    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'status' => 'active']))
        ->assertOk()
        ->assertSee('Pendingrow')
        ->assertDontSee('Historyrow');
});

it('falls back to all statuses for an invalid status parameter', function () {
    phase3b2aAppointment(phase3b2aPatient('Cancelledb', 'Fallbackrow'), [
        'status' => Appointment::STATUS_CANCELLED,
    ]);
    phase3b2aAppointment(phase3b2aPatient('Declinedb', 'Declinedfallbackrow'), [
        'status' => Appointment::STATUS_DECLINED,
        'decline_reason' => 'Slot unavailable',
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'status' => 'not_a_status']))
        ->assertOk()
        ->assertSee('Fallbackrow')
        ->assertSee('Declinedfallbackrow');
});

it('preserves an explicitly selected status and type across calendar navigation links', function () {
    $html = $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', [
            'view' => 'month',
            'date' => '2026-11-05',
            'status' => 'cancelled',
            'type' => 'checkup',
        ]))
        ->assertOk()
        ->getContent();

    // Both selects reflect the explicit selections (never forced to All).
    expect($html)
        ->toContain('value="cancelled" selected')
        ->toContain('value="checkup" selected');

    // The Prev/Next period links, the Today button and the Month/Year/Day
    // view-switch links are all built through the shared $baseUrl closure,
    // so each one carries the status and type filters forward.
    expect(substr_count($html, 'status=cancelled'))->toBeGreaterThanOrEqual(4)
        ->and(substr_count($html, 'type=checkup'))->toBeGreaterThanOrEqual(4);
});

it('shows cancelled and declined rows under the all-statuses filter', function () {
    phase3b2aAppointment(phase3b2aPatient('Alla', 'Cancelledrow'), [
        'status' => Appointment::STATUS_CANCELLED,
    ]);
    phase3b2aAppointment(phase3b2aPatient('Allb', 'Declinedrow'), [
        'status' => Appointment::STATUS_DECLINED,
        'decline_reason' => 'Slot unavailable',
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'status' => 'all']))
        ->assertOk()
        ->assertSee('Cancelledrow')
        ->assertSee('Declinedrow');
});

it('narrows results by service type', function () {
    phase3b2aAppointment(phase3b2aPatient('Typea', 'Checkuponly'), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
    ]);
    phase3b2aAppointment(phase3b2aPatient('Typeb', 'Counselonly'), [
        'appointment_type' => Appointment::TYPE_COUNSELING,
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'type' => 'checkup']))
        ->assertOk()
        ->assertSee('Checkuponly')
        ->assertDontSee('Counselonly');

    // Legacy type filters are no longer offered; an old URL falls back to
    // All Types so historical appointments remain visible.
    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'type' => 'counseling']))
        ->assertOk()
        ->assertSee('Checkuponly')
        ->assertSee('Counselonly');
});

it('falls back to safe defaults for invalid view date type and status parameters', function () {
    // The invalid date falls back to the clinic's today (month view), so
    // only page-shell rendering is asserted - no fixture date dependency.
    $response = $this->actingAs(phase3b2aAdmin())->get(route('appointments.index', [
        'view' => 'bogus-view',
        'date' => '2026-99-99',
        'type' => 'bogus-type',
        'status' => 'bogus-status',
    ]));

    $response->assertOk()
        ->assertSee('Service Type')
        ->assertSee('Month');
});

/*
|--------------------------------------------------------------------------
| 14-15. Clock-time handling (never invented, never shifted)
|--------------------------------------------------------------------------
*/

it('never invents a clock time for a date-only appointment', function () {
    phase3b2aAppointment(phase3b2aPatient('Timea', 'Dateonlyrow'), [
        'requested_time' => null,
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => null,
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertSee('No time set')
        ->assertDontSee('00:00');
});

it('displays recorded clinic wall times exactly as stored', function () {
    phase3b2aAppointment(phase3b2aPatient('Timeb', 'Walltime'), [
        'requested_time' => '10:30',
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertSee('10:30');
});

/*
|--------------------------------------------------------------------------
| 16-18. Year / Month / Day payloads
|--------------------------------------------------------------------------
*/

it('lists all twelve months with counts in the year view', function () {
    phase3b2aAppointment(phase3b2aPatient('Yeara', 'Novemberrow'), [
        'status' => Appointment::STATUS_PENDING,
        'requested_date' => '2026-11-05',
    ]);

    $response = $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'year', 'date' => '2026-06-15']));

    $response->assertOk()
        ->assertSee('2026')
        ->assertSee('January')
        ->assertSee('November')
        ->assertSee('December')
        ->assertSee('1 pending');
});

it('caps month cells at three chips with a plus-more link', function () {
    foreach (['Chipa', 'Chipb', 'Chipc', 'Chipd'] as $index => $lastName) {
        phase3b2aAppointment(phase3b2aPatient("Chip{$index}", $lastName), [
            'requested_date' => '2026-11-05',
            'requested_time' => sprintf('%02d:00', 8 + $index),
        ]);
    }

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15']))
        ->assertOk()
        ->assertSee('+1 more');
});

it('sorts day view events date-only first then by clock time', function () {
    phase3b2aAppointment(phase3b2aPatient('Sorta', 'Afternoonsort'), [
        'requested_time' => '14:00',
    ]);
    phase3b2aAppointment(phase3b2aPatient('Sortb', 'Morningsort'), [
        'requested_time' => '08:15',
    ]);
    phase3b2aAppointment(phase3b2aPatient('Sortc', 'AllDaysort'), [
        'requested_time' => null,
    ]);

    $content = $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->getContent();

    $allDay = strpos($content, 'Sortc AllDaysort');
    $morning = strpos($content, 'Sortb Morningsort');
    $afternoon = strpos($content, 'Sorta Afternoonsort');

    // Both the day rows and the details JSON preserve the sort order:
    // date-only first, then ascending clock time.
    expect($allDay)->not->toBeFalse()
        ->and($allDay)->toBeLessThan($morning)
        ->and($morning)->toBeLessThan($afternoon);
});

/*
|--------------------------------------------------------------------------
| 19-21. Embedded read-only details panel
|--------------------------------------------------------------------------
*/

it('embeds patient full name contact and profile link in the details payload', function () {
    $patient = phase3b2aPatient('Detaila', 'Panelname', '09998887777');
    phase3b2aAppointment($patient);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15']))
        ->assertOk()
        ->assertSee('appointmentDetailsData', false)
        ->assertSee('"patientFullName":"Detaila Panelname"', false)
        ->assertSee('"patientContact":"09998887777"', false)
        ->assertSee(route('patients.show', $patient->id), false);
});

it('degrades to a safe state when the patient record is archived', function () {
    $patient = phase3b2aPatient('Archiveda', 'Softdeleted');
    phase3b2aAppointment($patient);
    $patient->delete(); // soft delete - appointments keep the FK history

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'status' => 'all']))
        ->assertOk()
        ->assertSee('Patient record unavailable', false)
        ->assertSee('"profileUrl":null', false);
});

it('includes reviewer name and decline reason in the details payload', function () {
    $staff = phase3b2aStaff();
    $appointment = phase3b2aAppointment(phase3b2aPatient('Reviewa', 'Declinedrow'), [
        'status' => Appointment::STATUS_DECLINED,
        'reviewed_by' => $staff->id,
        'reviewed_at' => '2026-10-01 08:00:00',
        'decline_reason' => 'Provider unavailable',
    ]);

    $this->actingAs(phase3b2aAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'status' => 'declined']))
        ->assertOk()
        ->assertSee('"reviewerName":"Calendar Staff"', false)
        ->assertSee('"declineReason":"Provider unavailable"', false);

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_DECLINED);
});

/*
|--------------------------------------------------------------------------
| 22-23. No-write guarantees
|--------------------------------------------------------------------------
*/

it('exposes only read-only GET routes under the appointments namespace', function () {
    $named = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'appointments.'));

    expect($named)->toHaveCount(8);

    // Browsing (calendar index + patient search + Phase 2 slot catalog)
    // is GET-only.
    $getOnly = $named->filter(fn ($route) => array_diff($route->methods(), ['GET', 'HEAD']) === []);
    expect($getOnly->map(fn ($route) => $route->getName())->sort()->values()->all())
        ->toBe(['appointments.index', 'appointments.patient-search', 'appointments.slots']);

    // Every other appointments route is a mutation (PATCH/POST only) - never GET.
    foreach ($named->reject(fn ($route) => array_diff($route->methods(), ['GET', 'HEAD']) === []) as $route) {
        expect(array_intersect($route->methods(), ['GET', 'HEAD']))->toBeEmpty();
    }
});

it('never mutates appointment rows while browsing every view and filter', function () {
    $appointment = phase3b2aAppointment(phase3b2aPatient('Mutatea', 'Untouched'));
    $before = $appointment->fresh()->getAttributes();

    $urls = [
        ['date' => '2026-11-05'],
        ['view' => 'month', 'date' => '2026-11-05'],
        ['view' => 'year', 'date' => '2026-11-05'],
        ['view' => 'day', 'date' => '2026-11-05'],
        ['view' => 'day', 'date' => '2026-11-05', 'type' => 'checkup', 'status' => 'active'],
        ['date' => '2026-11-05', 'status' => 'all'],
        ['date' => '2026-11-05', 'status' => 'cancelled'],
    ];

    foreach ($urls as $query) {
        $this->actingAs(phase3b2aAdmin())
            ->get(route('appointments.index', $query))
            ->assertOk();
    }

    expect($appointment->fresh()->getAttributes())->toEqual($before);
});
