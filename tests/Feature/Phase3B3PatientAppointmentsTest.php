<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3B.3 - Patient Portal Online Appointment Requests
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers: My Appointments page,
| the request form + submission workflow, server-side patient identity
| enforcement (forged ids/status/reviewer/confirmed are ignored), the
| schedule rules (past dates/times, clinic hours, delivery 24/7 and
| date-only), provisional duplicate handling, three-section display with
| confirmed/declined/cancelled/completed rendering, Admin/Staff calendar
| reflection, the full authorization matrix (guest/admin/staff/unlinked/
| archived), and zero side effects (no admission, no bed, no clinical
| record, no audit entry, no patient row created). Phase 3B.4 adds clinic
| notifications for committed online requests, so the side-effect test
| now asserts audit stays empty while active clinic users are notified.
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3b3User(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3b3Patient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 28,
        'email' => "phase3b3{$sequence}@portal.test",
        'contact_number' => '09171234567',
        'address_line' => '123 Mabini Street',
        'barangay' => 'Sampaloc',
        'city_municipality' => 'Manila',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3b3Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3b3Eligible(): array
{
    $user = phase3b3User('patient');
    $patient = phase3b3Patient();
    phase3b3Link($patient, $user);

    return [$user, $patient];
}

function phase3b3Appointment(Patient $patient, array $overrides = []): Appointment
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

/** POST helper that starts from the create form (preserves old input). */
function phase3b3Post(User $user, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.create'))
        ->post(route('patient.appointments.store'), $data);
}

/** A future Manila date, computed from the frozen clock (or now). */
function phase3b3FutureDate(int $daysAhead = 7): string
{
    return Carbon::now('Asia/Manila')->startOfDay()->addDays($daysAhead)->toDateString();
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3b3Freeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 1-6. My Appointments page + navigation + authorization
|--------------------------------------------------------------------------
*/

it('lets an eligible patient open My Appointments', function () {
    [$user] = phase3b3Eligible();

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('My Appointments')
        ->assertSee('Pending Requests')
        ->assertSee('Upcoming Confirmed Appointments')
        ->assertSee('Appointment History')
        ->assertSee('need clinic confirmation');
});

it('links My Appointments from the portal navigation and the dashboard card', function () {
    [$user] = phase3b3Eligible();

    $response = $this->actingAs($user)->get(route('patient.portal.coming-soon'));

    $response->assertOk()
        ->assertSee('href="' . route('patient.appointments.index') . '"', false)
        ->assertSee('My Appointments')
        ->assertSee('Request checkup appointments online and track their status.');

    // The dashboard no longer advertises appointments as coming soon.
    $response->assertDontSee('Appointment services will be available soon.')
        ->assertDontSee('appointments and medical records are coming soon');
});

it('keeps Admin away from the patient-only appointment routes', function () {
    $admin = phase3b3User('admin');

    $this->actingAs($admin)->get(route('patient.appointments.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('patient.appointments.create'))->assertForbidden();
    $this->actingAs($admin)->post(route('patient.appointments.store'), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ])->assertForbidden();

    expect(Appointment::count())->toBe(0);
});

it('keeps Staff away from the patient-only appointment routes', function () {
    $staff = phase3b3User('staff');

    $this->actingAs($staff)->get(route('patient.appointments.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('patient.appointments.create'))->assertForbidden();
    $this->actingAs($staff)->post(route('patient.appointments.store'), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ])->assertForbidden();

    expect(Appointment::count())->toBe(0);
});

it('redirects a guest to login on all three appointment routes', function () {
    $this->get(route('patient.appointments.index'))->assertRedirect(route('login'));
    $this->get(route('patient.appointments.create'))->assertRedirect(route('login'));
    $this->post(route('patient.appointments.store'), [])->assertRedirect(route('login'));
});

it('denies an unlinked patient account with the friendly message', function () {
    $user = phase3b3User('patient');

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    $this->actingAs($user)->get(route('patient.appointments.create'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    $this->actingAs($user)->post(route('patient.appointments.store'), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ])->assertForbidden();

    expect(Appointment::count())->toBe(0);
});

it('denies an archived patient account', function () {
    $user = phase3b3User('patient');
    $patient = phase3b3Patient();
    phase3b3Link($patient, $user);
    $patient->delete();

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertForbidden()
        ->assertSee('Please contact the clinic for assistance.');

    $this->actingAs($user)->post(route('patient.appointments.store'), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ])->assertForbidden();

    expect(Appointment::count())->toBe(0);
});

it('sends a flagged patient to the mandatory password change', function () {
    $user = phase3b3User('patient');
    $user->forceFill(['must_change_password' => true])->save();
    $patient = phase3b3Patient();
    phase3b3Link($patient, $user);

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertRedirect(route('patient.change-initial-password.edit'));

    $this->actingAs($user)->post(route('patient.appointments.store'), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.change-initial-password.edit'));

    expect(Appointment::count())->toBe(0);
});

it('treats a soft-deleted user as a guest', function () {
    $user = phase3b3User('patient');
    $patient = phase3b3Patient();
    phase3b3Link($patient, $user);
    $user->delete();

    $sessionKey = 'login_web_' . sha1(SessionGuard::class);

    $this->withSession([$sessionKey => $user->id])
        ->get(route('patient.appointments.index'))
        ->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| 7-14. Creating requests: identity, source, status, forged input
|--------------------------------------------------------------------------
*/

it('lets a patient create a checkup request that is confirmed immediately', function () {
    [$user, $patient] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'))
        ->assertSessionHas('success', 'Your checkup is confirmed. Your selected time slot is now reserved for you.');

    $appointment = Appointment::sole();
    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->appointment_type)->toBe(Appointment::TYPE_CHECKUP)
        ->and($appointment->source)->toBe(Appointment::SOURCE_ONLINE)
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->requested_date->toDateString())->toBe($date)
        ->and((string) $appointment->requested_time)->toContain('10:00')
        ->and($appointment->confirmed_date->toDateString())->toBe($date)
        ->and((string) $appointment->confirmed_time)->toContain('10:00')
        ->and($appointment->reviewed_by)->toBeNull()
        ->and($appointment->reviewed_at)->toBeNull()
        ->and($appointment->decline_reason)->toBeNull()
        ->and($appointment->cancellation_reason)->toBeNull();
});

it('rejects new counseling requests through the patient booking endpoint', function () {
    [$user] = phase3b3Eligible();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '14:30',
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe(0);
});

it('rejects new delivery requests through the patient booking endpoint', function () {
    [$user] = phase3b3Eligible();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '23:30',
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe(0);
});

it('rejects legacy request types at the service write boundary', function () {
    [, $patient] = phase3b3Eligible();
    $service = app(\App\Services\AppointmentManagementService::class);

    expect(fn () => $service->createOnlineRequest(
        $patient,
        Appointment::TYPE_COUNSELING,
        phase3b3FutureDate(),
        '10:00'
    ))->toThrow(\DomainException::class, 'Routine appointment booking currently supports checkups only.');

    expect(fn () => $service->createWalkIn(
        phase3b3User('staff'),
        $patient->id,
        Appointment::TYPE_DELIVERY_REQUEST,
        phase3b3FutureDate(),
        null
    ))->toThrow(\DomainException::class, 'Routine appointment booking currently supports checkups only.');

    expect(Appointment::count())->toBe(0);
});

it('ignores forged server-owned fields and system-confirms an online checkup', function () {
    [$user, $patient] = phase3b3Eligible();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
        // Forged server-owned fields - must all be ignored.
        'patient_id' => 999999,
        'user_id' => 123456,
        'status' => Appointment::STATUS_CONFIRMED,
        'source' => Appointment::SOURCE_WALK_IN,
        'confirmed_date' => '2026-01-01',
        'confirmed_time' => '08:00',
        'reviewed_by' => 1,
        'reviewed_at' => '2026-01-01 08:00:00',
        'decline_reason' => 'forged',
        'cancellation_reason' => 'forged',
    ])->assertRedirect(route('patient.appointments.index'));

    $appointment = Appointment::sole();
    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->source)->toBe(Appointment::SOURCE_ONLINE)
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date->toDateString())->toBe($appointment->requested_date->toDateString())
        ->and((string) $appointment->confirmed_time)->toContain('10:00')
        ->and($appointment->reviewed_by)->toBeNull()
        ->and($appointment->reviewed_at)->toBeNull()
        ->and($appointment->decline_reason)->toBeNull()
        ->and($appointment->cancellation_reason)->toBeNull()
        ->and(Appointment::whereKey(999999)->exists())->toBeFalse();
});

it('assigns the patient id from the authenticated identity, never from input', function () {
    [$user, $patient] = phase3b3Eligible();
    $other = phase3b3Patient(['first_name' => 'Foreigner', 'last_name' => 'Outsider']);

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
        'patient_id' => $other->id,
    ]);

    $appointment = Appointment::sole();
    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->patient_id)->not->toBe($other->id);
});

it('shows only the patient own appointments on My Appointments', function () {
    [$user, $patient] = phase3b3Eligible();
    $foreigner = phase3b3Patient(['first_name' => 'Foreigner', 'last_name' => 'Outsider']);
    phase3b3Appointment($patient, ['requested_date' => phase3b3FutureDate(3)]);
    phase3b3Appointment($foreigner, [
        'requested_date' => phase3b3FutureDate(4),
        'requested_time' => '11:00',
    ]);

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Pending Requests')
        ->assertDontSee('Foreigner')
        ->assertDontSee('Outsider')
        ->assertDontSee('11:00');
});

it('ignores a patient_id supplied as a query parameter on the list page', function () {
    [$user] = phase3b3Eligible();
    $other = phase3b3Patient(['first_name' => 'Foreigner', 'last_name' => 'Outsider']);
    phase3b3Appointment($other, ['requested_date' => phase3b3FutureDate()]);

    $this->actingAs($user)
        ->get(route('patient.appointments.index') . '?patient_id=' . $other->id)
        ->assertOk()
        ->assertDontSee('Foreigner')
        ->assertDontSee('Outsider');

    // There is no ID-based appointment URL to reach.
    $this->actingAs($user)->get('/patient/appointments/' . $other->id)->assertNotFound();
    $this->actingAs($user)->get('/patient/appointments/' . $user->id)->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 16-22. Schedule rules: past, clinic hours, delivery 24/7
|--------------------------------------------------------------------------
*/

it('rejects a past requested date', function () {
    [$user] = phase3b3Eligible();
    phase3b3Freeze('2026-10-10 10:00:00');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-10-09',
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('requested_date');

    expect(Appointment::count())->toBe(0);
});

it("rejects today's past requested time and allows today's future time", function () {
    [$user] = phase3b3Eligible();
    phase3b3Freeze('2026-10-10 14:30:00');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-10-10',
        'requested_time' => '09:00',
    ])->assertSessionHasErrors('requested_time');

    expect(Appointment::count())->toBe(0);

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-10-10',
        'requested_time' => '15:00',
    ])->assertRedirect(route('patient.appointments.index'));

    expect(Appointment::count())->toBe(1);
});

it('allows the equal-minute boundary on today', function () {
    [$user] = phase3b3Eligible();
    phase3b3Freeze('2026-10-10 14:30:00');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-10-10',
        'requested_time' => '14:30',
    ])->assertRedirect(route('patient.appointments.index'));

    expect(Appointment::count())->toBe(1);
});

it('enforces checkup clinic hours at the request layer', function () {
    [$user] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '07:59',
    ])->assertSessionHasErrors('requested_time');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '17:00',
    ])->assertSessionHasErrors('requested_time');

    // Phase 2: non-grid times are refused too (slot starts only).
    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '16:59',
    ])->assertSessionHasErrors('requested_time');

    // The last valid grid slot start (16:30, ends at clinic close 17:00).
    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '16:30',
    ])->assertRedirect(route('patient.appointments.index'));

    expect(Appointment::count())->toBe(1);
});

it('rejects counseling before schedule validation at the request layer', function () {
    [$user] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => $date,
        'requested_time' => '07:30',
    ])->assertSessionHasErrors('appointment_type');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => $date,
        'requested_time' => '08:00',
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe(0);
});

it('requires a time for checkups and rejects legacy types with or without one', function () {
    [$user] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
    ])->assertSessionHasErrors('requested_time');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => $date,
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe(0);

    // Delivery remains a legacy appointment type, unavailable for new requests.
    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => $date,
    ])->assertSessionHasErrors('appointment_type');
    expect(Appointment::count())->toBe(0);
});

it('rejects delivery requests regardless of the submitted time', function () {
    [$user] = phase3b3Eligible();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => phase3b3FutureDate(1),
        'requested_time' => '23:30',
    ])->assertSessionHasErrors('appointment_type');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => phase3b3FutureDate(2),
        'requested_time' => '02:15',
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe(0);
});

it('rejects an invalid appointment type and an invalid date format', function () {
    [$user] = phase3b3Eligible();

    phase3b3Post($user, [
        'appointment_type' => 'teleconsult',
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('appointment_type');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '10-11-2026',
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('requested_date');

    // A valid type with missing date/time - date and time errors only.
    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
    ])->assertSessionHasErrors(['requested_date', 'requested_time'])
        ->assertSessionDoesntHaveErrors('appointment_type');

    expect(Appointment::count())->toBe(0);
});

it('preserves old input on a validation failure', function () {
    [$user] = phase3b3Eligible();
    phase3b3Freeze('2026-10-10 10:00:00');

    $response = phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => '2026-10-09',
        'requested_time' => '09:00',
    ]);

    $response->assertSessionHasErrors('requested_date')
        ->assertSessionHas('_old_input');

    // Phase 4: the old time value is carried by the slot picker's
    // validated preselection (initial config), not a free-form
    // value attribute; the old date value stays on the date input.
    $this->actingAs($user)->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee('value="2026-10-09"', false)
        ->assertSee('\u0022initial\u0022:\u002209:00\u0022', false);
});

/*
|--------------------------------------------------------------------------
| 23-24. Duplicate handling
|--------------------------------------------------------------------------
*/

it('rejects an exact duplicate active request but allows after decline', function () {
    [$user, $patient] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'));

    // Exact duplicate (pending) - refused server-side.
    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('appointment')
        ->assertSessionHas('_old_input');

    expect(Appointment::count())->toBe(1);

    // A different time on the same date is NOT a duplicate.
    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '14:00',
    ])->assertRedirect(route('patient.appointments.index'));

    expect(Appointment::count())->toBe(2);

    // A different type at a different time is NOT a duplicate (a confirmed
    // checkup now occupies its slot for every type).
    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => $date,
        'requested_time' => '11:00',
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe(2);

    // Cancel the first (auto-confirmed) checkup: history and its released
    // hold do not block a new identical one.
    $first = Appointment::orderBy('id')->first();
    $first->forceFill(['status' => Appointment::STATUS_CANCELLED, 'cancellation_reason' => 'Test'])->save();
    \App\Models\AppointmentSlotHold::query()->where('appointment_id', $first->id)->delete();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'));

    expect(Appointment::count())->toBe(3);
});

it('treats a confirmed appointment as an active duplicate blocker', function () {
    [$user, $patient] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Appointment($patient, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '10:00',
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_date' => $date,
        'confirmed_time' => '10:00',
        'reviewed_by' => phase3b3User('staff')->id,
        'reviewed_at' => now(),
    ]);

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('appointment');

    expect(Appointment::count())->toBe(1);
});

it('refuses a different patient the identical checkup slot (first come first served)', function () {
    [$userA] = phase3b3Eligible();
    [$userB] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    $payload = [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ];

    phase3b3Post($userA, $payload)->assertRedirect(route('patient.appointments.index'));
    phase3b3Post($userB, $payload)->assertSessionHasErrors('appointment');

    expect(Appointment::count())->toBe(1)
        ->and(Appointment::where('status', Appointment::STATUS_CONFIRMED)->count())->toBe(1);
});

it('does not impose a one-appointment-per-day restriction', function () {
    [$user] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => '09:00',
    ])->assertRedirect(route('patient.appointments.index'));

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('appointment_type');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => $date,
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 25-27. Display: confirmed schedule, reasons, history
|--------------------------------------------------------------------------
*/

it('shows the confirmed schedule for a confirmed appointment', function () {
    [$user, $patient] = phase3b3Eligible();
    $staff = phase3b3User('staff');

    $appointment = phase3b3Appointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '09:30',
        'reviewed_by' => $staff->id,
        'reviewed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('patient.appointments.index'));

    $response->assertOk()
        ->assertSee('Upcoming Confirmed Appointments')
        ->assertSee('Clinic-confirmed schedule:')
        ->assertSee('November 6, 2026')
        ->assertSee('09:30')
        ->assertSee('Confirmed')
        // Staff-only audit details are not exposed to the patient.
        ->assertDontSee($staff->name)
        ->assertDontSee('reviewed_by')
        ->assertDontSee('Not yet reviewed');
});

it('shows the decline reason on a declined appointment in history', function () {
    [$user, $patient] = phase3b3Eligible();

    phase3b3Appointment($patient, [
        'status' => Appointment::STATUS_DECLINED,
        'decline_reason' => 'The clinic is fully booked on that date.',
        'reviewed_by' => phase3b3User('staff')->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Appointment History')
        ->assertSee('Declined')
        ->assertSee('Decline reason:')
        ->assertSee('The clinic is fully booked on that date.');
});

it('shows the cancellation reason on a cancelled appointment in history', function () {
    [$user, $patient] = phase3b3Eligible();

    phase3b3Appointment($patient, [
        'status' => Appointment::STATUS_CANCELLED,
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
        'cancellation_reason' => 'Clinic closed for a holiday.',
        'reviewed_by' => phase3b3User('staff')->id,
        'reviewed_at' => now(),
    ]);

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Cancelled')
        ->assertSee('Cancellation reason:')
        ->assertSee('Clinic closed for a holiday.');
});

it('shows completed appointments in history with the awaiting-clinification phrase absent', function () {
    [$user, $patient] = phase3b3Eligible();

    phase3b3Appointment($patient, [
        'status' => Appointment::STATUS_COMPLETED,
        'confirmed_date' => '2026-09-01',
        'confirmed_time' => '10:00',
        'reviewed_by' => phase3b3User('staff')->id,
        'reviewed_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('patient.appointments.index'));

    $response->assertOk()
        ->assertSee('Appointment History')
        ->assertSee('Completed')
        ->assertDontSee('Awaiting clinic confirmation');
});

it('shows the pending section with the awaiting-confirmation phrase', function () {
    [$user, $patient] = phase3b3Eligible();
    phase3b3Appointment($patient, ['requested_date' => phase3b3FutureDate()]);

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Pending Requests')
        ->assertSee('Awaiting clinic confirmation')
        ->assertSee('Booked via Online');
});

it('shows a clear empty state when the patient has no appointments', function () {
    [$user] = phase3b3Eligible();

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('You have no pending appointment requests.')
        ->assertSee('You have no upcoming confirmed appointments.')
        ->assertSee('You have no appointment history yet.');
});

it('shows a checkup-only request form', function () {
    [$user] = phase3b3Eligible();

    $this->actingAs($user)->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee('Request an Appointment')
        ->assertSee('Checkup')
        ->assertDontSee('Delivery Request')
        ->assertDontSee('Counseling')
        ->assertSee('8:00 AM to before 5:00 PM')
        ->assertSee('name="appointment_type"', false)
        ->assertSee('name="requested_date"', false)
        // Phase 4: requested_time is the shared slot picker's carrier
        // (bound through the picker config), not a free-form input.
        ->assertSee('\u0022timeName\u0022:\u0022requested_time\u0022', false)
        ->assertSee('data-slot-carrier', false)
        ->assertDontSee('name="patient_id"', false)
        ->assertDontSee('name="status"', false)
        ->assertDontSee('name="source"', false)
        ->assertDontSee('name="confirmed_date"', false)
        ->assertDontSee('name="reviewed_by"', false);
});

/*
|--------------------------------------------------------------------------
| 28-30. Side effects, calendar reflection, partial records
|--------------------------------------------------------------------------
*/

it('creates no admission, bed, occupancy or clinical record', function () {
    [$user] = phase3b3Eligible();

    $tablesBefore = [
        'prenatal_visits' => DB::table('prenatal_visits')->count(),
        'medical_histories' => DB::table('medical_histories')->count(),
        'birth_plans' => DB::table('birth_plans')->count(),
        'ultrasounds' => DB::table('ultrasounds')->count(),
        'pregnancy_outcomes' => DB::table('pregnancy_outcomes')->count(),
        'babies' => DB::table('babies')->count(),
        'referrals' => DB::table('referrals')->count(),
        'users' => DB::table('users')->count(),
        'patients' => DB::table('patients')->count(),
        'audit_logs' => DB::table('audit_logs')->count(),
    ];

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '03:00',
    ])->assertSessionHasErrors('appointment_type');

    // No appointment row or related side effect was created.
    expect(Appointment::count())->toBe(0);

    foreach ($tablesBefore as $table => $count) {
        expect(DB::table($table)->count(), "table {$table} must be unchanged")->toBe($count);
    }

    // No bed/occupancy column exists on the appointments row at all.
    $columns = array_map(fn ($column) => $column['name'], Schema::getColumns('appointments'));
    foreach (['bed', 'admitted_at', 'discharged_at', 'occupancy', 'room'] as $forbidden) {
        expect($columns)->not->toContain($forbidden);
    }
});

it('writes one auto-confirm audit entry and notifies clinic users and the patient', function () {
    [$user] = phase3b3Eligible();
    phase3b3User('admin');
    phase3b3User('staff');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'));

    // FCFS Phase 2: one in-transaction audit row, two clinic alerts
    // (admin + staff) and one patient confirmation.
    expect(AuditLog::count())->toBe(1)
        ->and(AuditLog::first()->action)->toBe('ONLINE_APPOINTMENT_AUTO_CONFIRMED')
        ->and(DB::table('notifications')->count())->toBe(3);
});

it('creates no partial record when validation fails', function () {
    [$user] = phase3b3Eligible();
    phase3b3Freeze('2026-10-10 10:00:00');

    $before = Appointment::count();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-10-09',
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('requested_date');

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-11-01',
        'requested_time' => '25:99',
    ])->assertSessionHasErrors('requested_time');

    phase3b3Post($user, [
        'appointment_type' => 'invalid',
        'requested_date' => '2026-11-01',
    ])->assertSessionHasErrors('appointment_type');

    expect(Appointment::count())->toBe($before);
});

it('reflects a newly submitted pending request on the Admin/Staff calendar', function () {
    [$user, $patient] = phase3b3Eligible();
    $date = phase3b3FutureDate();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('appointment_type');

    $admin = phase3b3User('admin');

    $historical = phase3b3Appointment($patient, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => $date,
        'requested_time' => '10:00',
    ]);

    $response = $this->actingAs($admin)->get(route('appointments.index', ['view' => 'day', 'date' => $date]));

    $response->assertOk()
        ->assertSee('Counseling')
        ->assertSee($patient->last_name)
        ->assertSee('Pending')
        ->assertSee('Online');

    // The same row is confirm-eligible through the existing staff workflow.
    $appointment = $historical;
    $this->actingAs($admin)->from(route('appointments.index'))->patch(
        route('appointments.confirm', $appointment),
        ['confirmed_date' => $date, 'confirmed_time' => '10:00']
    )->assertRedirect(route('appointments.index'))->assertSessionHas('success');

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('does not add unexpected appointment tables or mutate the appointments schema', function () {
    [$user] = phase3b3Eligible();

    phase3b3Post($user, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3b3FutureDate(),
        'requested_time' => '10:00',
    ]);

    // The canonical appointments table, the approved Phase 3D.2
    // change-request table, and the approved Phase 1 slot-hold ledger
    // (empty by design - no backfill) are the ONLY appointment-bearing
    // tables. This is an exact-set assertion: any unknown fourth table
    // fails. The appointments schema itself remains unmutated.
    $appointmentTables = array_values(array_filter(
        Schema::getTables(),
        fn (array $table) => str_contains(strtolower($table['name']), 'appointment')
    ));

    $names = array_map(
        fn (array $table) => strtolower($table['name']),
        $appointmentTables
    );
    sort($names);

    expect($names)->toBe([
        'appointment_change_requests',
        'appointment_slot_holds',
        'appointments',
    ]);
});
it("Phase 3: next_visit_time column exists", function () {
    $hasColumn = DB::connection()->getSchemaBuilder()->hasColumn("prenatal_visits", "next_visit_time");
    expect($hasColumn)->toBeTrue();
});

it("Phase 3: prenatal_visit_id FK column exists on appointments", function () {
    $hasColumn = DB::connection()->getSchemaBuilder()->hasColumn("appointments", "prenatal_visit_id");
    expect($hasColumn)->toBeTrue();
});

it("Phase 3: staff can store date+time creating follow-up appointment", function () {
    $user = User::factory()->create(["role" => "staff"]);
    $patient = new User(["role" => "patient"]);
    $patient->save();
    $payload = ["patient_id" => $patient->id, "next_visit_date" => now()->addDays(7)->toDateString(), "next_visit_time" => "09:00", "hypertension" => "1"];
    $response = $this->actingAs($user)->post(route("prenatal-visits.store"), $payload);
    $response->assertRedirect();
    $visit = PrenatalVisit::latest("id")->first();
    expect($visit->next_visit_time)->toBe("09:00");
    $appointment = $visit->activeFollowUpAppointment();
    expect($appointment)->not->toBeNull();
});

it("Phase 3: date-only store creates no appointment", function () {
    $user = User::factory()->create(["role" => "staff"]);
    $patient = new User(["role" => "patient"]);
    $patient->save();
    $payload = ["patient_id" => $patient->id, "next_visit_date" => now()->addDays(7)->toDateString()];
    $response = $this->actingAs($user)->post(route("prenatal-visits.store"), $payload);
    $response->assertRedirect();
    $visit = PrenatalVisit::latest("id")->first();
    expect($visit->next_visit_time)->toBeNull();
});

it("Phase 3: SOURCE_STAFF_FOLLOWUP in Appointment::SOURCES", function () {
    expect(Appointment::SOURCES)->toContain("staff_followup");
});
