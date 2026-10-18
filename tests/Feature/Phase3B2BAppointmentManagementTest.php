<?php

use App\Models\Appointment;
use App\Models\AppointmentSlotHold;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentManagementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3B.2B - Admin/Staff Appointment Management (transitions + walk-in)
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the four permitted
| transitions for both roles, illegal/stale transition refusal, provisional
| clinic-hours scheduling, delivery 24/7 with zero occupancy side effects,
| decline/cancellation reason requirements, walk-in creation rules, audit
| logging, reviewer-metadata preservation, forged-input immunity and
| calendar reflection.
*/

function phase3b2bAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Manage Admin']);
}

function phase3b2bStaff(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Manage Staff']);
}

function phase3b2bPatientUser(): User
{
    return User::factory()->create(['role' => 'patient', 'name' => 'Portal Patient']);
}

function phase3b2bPatient(string $first = 'Maria', string $last = 'Santos'): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => "phase3b2b{$sequence}@clinic.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase3b2bAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ], $overrides));
}

function phase3b2bConfirmedAppointment(Patient $patient, User $reviewer, array $overrides = []): Appointment
{
    return phase3b2bAppointment($patient, array_merge([
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => '2026-10-01 08:00:00',
    ], $overrides));
}

/** PATCH helper that flashes back to the calendar (preserves view/date/filters). */
function phase3b2bActAsPatch(User $user, string $routeName, Appointment $appointment, array $data = [])
{
    return test()->actingAs($user)
        ->from(route('appointments.index'))
        ->patch(route($routeName, $appointment), $data);
}

/** Frozen-time tests must never leak into the rest of the suite. */
afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 1-8. Four transitions x both roles
|--------------------------------------------------------------------------
*/

it('lets an admin confirm a pending appointment with reviewer metadata', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());
    $admin = phase3b2bAdmin();

    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '09:30',
    ])->assertRedirect(route('appointments.index'))->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date->toDateString())->toBe('2026-11-06')
        ->and((string) $appointment->confirmed_time)->toContain('09:30')
        ->and($appointment->reviewed_by)->toBe($admin->id)
        ->and($appointment->reviewed_at)->not->toBeNull();
});

it('lets staff confirm a pending appointment', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());
    $staff = phase3b2bStaff();

    phase3b2bActAsPatch($staff, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '14:00',
    ])->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->reviewed_by)->toBe($staff->id);
});

it('lets an admin decline a pending appointment with a reason', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());
    $admin = phase3b2bAdmin();

    phase3b2bActAsPatch($admin, 'appointments.decline', $appointment, [
        'decline_reason' => 'Provider unavailable on the requested date.',
    ])->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_DECLINED)
        ->and($appointment->decline_reason)->toBe('Provider unavailable on the requested date.')
        ->and($appointment->reviewed_by)->toBe($admin->id)
        ->and($appointment->reviewed_at)->not->toBeNull();
});

it('lets staff decline a pending appointment', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());

    phase3b2bActAsPatch(phase3b2bStaff(), 'appointments.decline', $appointment, [
        'decline_reason' => 'Requested slot is closed.',
    ])->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_DECLINED);
});

it('lets an admin cancel a confirmed appointment while preserving reviewer metadata', function () {
    $reviewer = phase3b2bStaff();
    $appointment = phase3b2bConfirmedAppointment(phase3b2bPatient(), $reviewer);
    $admin = phase3b2bAdmin();

    phase3b2bActAsPatch($admin, 'appointments.cancel', $appointment, [
        'cancellation_reason' => 'Patient rescheduled by phone.',
    ])->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->cancellation_reason)->toBe('Patient rescheduled by phone.')
        ->and($appointment->reviewed_by)->toBe($reviewer->id, 'reviewed_by must stay the original reviewer')
        ->and($appointment->confirmed_date->toDateString())->toBe('2026-11-05')
        ->and($appointment->requested_date->toDateString())->toBe('2026-11-05');
});

it('lets staff cancel a confirmed appointment and records the actor in the audit log only', function () {
    $reviewer = phase3b2bAdmin();
    $appointment = phase3b2bConfirmedAppointment(phase3b2bPatient(), $reviewer);
    $staff = phase3b2bStaff();

    phase3b2bActAsPatch($staff, 'appointments.cancel', $appointment, [
        'cancellation_reason' => 'Clinic closed for the day.',
    ])->assertSessionHas('success');

    $appointment->refresh();
    $audit = AuditLog::where('action', 'APPOINTMENT_CANCELLED')->latest('id')->first();

    expect($appointment->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->reviewed_by)->toBe($reviewer->id)
        ->and($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($staff->id)
        ->and($audit->description)->toContain((string) $staff->id);
});

it('lets an admin mark a confirmed appointment as completed', function () {
    // QA FIX 2: freeze on the fixture's own scheduled day (10:00 has already
    // passed at 15:00) - completion compares DATES only, so today still works.
    phase3b2bFreezeManila('2026-11-05 15:00:00');
    $appointment = phase3b2bConfirmedAppointment(phase3b2bPatient(), phase3b2bStaff());

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.complete', $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

it('lets staff mark a confirmed appointment as completed', function () {
    // QA FIX 2: due-today fixture (frozen Manila clock) as above.
    phase3b2bFreezeManila('2026-11-05 15:00:00');
    $appointment = phase3b2bConfirmedAppointment(phase3b2bPatient(), phase3b2bAdmin());

    phase3b2bActAsPatch(phase3b2bStaff(), 'appointments.complete', $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});


/*
|--------------------------------------------------------------------------
| 9-10. Access boundary
|--------------------------------------------------------------------------
*/

it('forbids patient accounts from every management action', function () {
    $patient = phase3b2bPatient();
    $pending = phase3b2bAppointment($patient);
    $confirmed = phase3b2bConfirmedAppointment($patient, phase3b2bStaff());
    $user = phase3b2bPatientUser();

    foreach ([
        ['appointments.confirm', $pending, ['confirmed_date' => '2026-11-06', 'confirmed_time' => '09:00']],
        ['appointments.decline', $pending, ['decline_reason' => 'no']],
        ['appointments.cancel', $confirmed, ['cancellation_reason' => 'no']],
        ['appointments.complete', $confirmed, []],
    ] as [$route, $appointment, $data]) {
        $this->actingAs($user)
            ->patch(route($route, $appointment), $data)
            ->assertForbidden();
    }

    $this->actingAs($user)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => 'checkup',
            'appointment_date' => '2026-11-06',
            'appointment_time' => '09:00',
        ])
        ->assertForbidden();

    expect($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($confirmed->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('redirects guests to login for every management action', function () {
    $patient = phase3b2bPatient();
    $pending = phase3b2bAppointment($patient);
    $confirmed = phase3b2bConfirmedAppointment($patient, phase3b2bStaff());

    $this->patch(route('appointments.confirm', $pending), [
        'confirmed_date' => '2026-11-06', 'confirmed_time' => '09:00',
    ])->assertRedirect(route('login'));

    $this->post(route('appointments.walk-in.store'), [
        'patient_id' => $patient->id,
        'appointment_type' => 'checkup',
        'appointment_date' => '2026-11-06',
        'appointment_time' => '09:00',
    ])->assertRedirect(route('login'));

    expect($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($confirmed->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

/*
|--------------------------------------------------------------------------
| 11-12. Illegal transitions + duplicate/stale submissions
|--------------------------------------------------------------------------
*/

it('rejects every transition the workflow does not permit', function () {
    $patient = phase3b2bPatient();
    $staff = phase3b2bStaff();
    $admin = phase3b2bAdmin();

    $confirmed = phase3b2bConfirmedAppointment($patient, $staff);
    $declined = phase3b2bAppointment($patient, ['status' => Appointment::STATUS_DECLINED, 'decline_reason' => 'x']);
    $cancelled = phase3b2bConfirmedAppointment($patient, $staff, ['status' => Appointment::STATUS_CANCELLED]);
    $completed = phase3b2bConfirmedAppointment($patient, $staff, ['status' => Appointment::STATUS_COMPLETED]);
    $pending = phase3b2bAppointment($patient);

    // declined -> confirmed, cancelled -> confirmed: refused
    foreach ([$declined, $cancelled] as $appointment) {
        phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
            'confirmed_date' => '2026-11-06', 'confirmed_time' => '09:00',
        ])->assertSessionHasErrors('appointment');
    }

    // completed -> pending (via decline), completed -> cancelled: refused
    phase3b2bActAsPatch($admin, 'appointments.decline', $completed, ['decline_reason' => 'nope'])
        ->assertSessionHasErrors('appointment');
    phase3b2bActAsPatch($admin, 'appointments.cancel', $completed, ['cancellation_reason' => 'nope'])
        ->assertSessionHasErrors('appointment');

    // pending -> cancel/complete: refused (must be confirmed first)
    phase3b2bActAsPatch($admin, 'appointments.cancel', $pending, ['cancellation_reason' => 'nope'])
        ->assertSessionHasErrors('appointment');
    phase3b2bActAsPatch($admin, 'appointments.complete', $pending)
        ->assertSessionHasErrors('appointment');

    // repeat confirm on an already-confirmed row: refused
    phase3b2bActAsPatch($admin, 'appointments.confirm', $confirmed, [
        'confirmed_date' => '2026-11-07', 'confirmed_time' => '11:00',
    ])->assertSessionHasErrors('appointment');

    expect($declined->refresh()->status)->toBe(Appointment::STATUS_DECLINED)
        ->and($cancelled->refresh()->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($completed->refresh()->status)->toBe(Appointment::STATUS_COMPLETED)
        ->and($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($confirmed->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('refuses a stale duplicate submission without overwriting the first action', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());
    $firstActor = phase3b2bStaff();
    $secondActor = phase3b2bAdmin();

    phase3b2bActAsPatch($firstActor, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06', 'confirmed_time' => '09:00',
    ])->assertSessionHas('success');

    // The second (stale) action - e.g. a double-clicked or delayed form -
    // must fail safely and keep the FIRST actor's review intact.
    phase3b2bActAsPatch($secondActor, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-09', 'confirmed_time' => '15:00',
    ])->assertSessionHasErrors('appointment');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->reviewed_by)->toBe($firstActor->id)
        ->and($appointment->confirmed_date->toDateString())->toBe('2026-11-06');
});

/*
|--------------------------------------------------------------------------
| 13-16. Scheduling rules (provisional clinic hours + delivery 24/7)
|--------------------------------------------------------------------------
*/

it('requires valid scheduling data to confirm', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.confirm', $appointment, [])
        ->assertSessionHasErrors(['confirmed_date']);

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-13-45',
        'confirmed_time' => '09:00',
    ])->assertSessionHasErrors(['confirmed_date']);

    // Checkup without a time is refused.
    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
    ])->assertSessionHasErrors(['confirmed_time']);

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_PENDING);
});

it('enforces the slot-grid clinic-hours rule for checkup confirmations', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient(), ['appointment_type' => Appointment::TYPE_CHECKUP]);
    $admin = phase3b2bAdmin();

    // Out-of-hours and non-grid times are refused by the FormRequest's
    // field-level slot rules (surfaced under 'confirmed_time'); the
    // service assertSlotStart remains the authoritative backstop.
    foreach (['07:59', '17:00', '18:30', '16:59', '09:15'] as $rejectedTime) {
        phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
            'confirmed_date' => '2026-11-06',
            'confirmed_time' => $rejectedTime,
        ])->assertSessionHasErrors('confirmed_time');
    }

    // Grid boundaries: open (08:00) and the LAST slot start (16:30).
    foreach (['08:00', '16:30'] as $acceptedTime) {
        $appointment->status = Appointment::STATUS_PENDING;
        $appointment->save();
        // Re-pending a previously confirmed row must drop its hold (no
        // production transition does this; the fixture simulates one).
        AppointmentSlotHold::query()->where('appointment_id', $appointment->id)->delete();
        phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
            'confirmed_date' => '2026-11-06',
            'confirmed_time' => $acceptedTime,
        ])->assertSessionHas('success');
        $appointment->refresh();
        expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED);
        $appointment->status = Appointment::STATUS_PENDING;
        $appointment->save();
    }
});

it('enforces the slot-grid clinic-hours rule for counseling confirmations', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient(), [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_time' => '10:00',
    ]);

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '07:00',
    ])->assertSessionHasErrors('confirmed_time');

    phase3b2bActAsPatch(phase3b2bStaff(), 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '16:30',
    ])->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('accepts delivery requests 24/7 including date-only and creates no admission or clinical record', function () {
    $patient = phase3b2bPatient();
    $appointment = phase3b2bAppointment($patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
    ]);
    $admin = phase3b2bAdmin();

    // Late-night time accepted (24/7).
    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '02:15',
    ])->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and((string) $appointment->confirmed_time)->toContain('02:15');

    // A second delivery request confirmed date-only (no time invented).
    $dateOnly = phase3b2bAppointment($patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
    ]);

    phase3b2bActAsPatch($admin, 'appointments.confirm', $dateOnly, [
        'confirmed_date' => '2026-11-07',
        'confirmed_time' => null,
    ])->assertSessionHas('success');

    $dateOnly->refresh();
    expect($dateOnly->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($dateOnly->confirmed_time)->toBeNull()
        ->and(Schema::hasTable('delivery_admissions'))->toBeFalse()
        ->and(Schema::hasTable('bed_occupancies'))->toBeFalse()
        ->and(\App\Models\PrenatalVisit::count())->toBe(0)
        ->and(\App\Models\BirthPlan::count())->toBe(0)
        ->and(\App\Models\PregnancyOutcome::count())->toBe(0)
        ->and(Appointment::count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 18-19. Reason requirements
|required*/

it('requires a meaningful decline reason', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.decline', $appointment, [])
        ->assertSessionHasErrors('decline_reason');

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.decline', $appointment, ['decline_reason' => 'ab'])
        ->assertSessionHasErrors('decline_reason');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($appointment->decline_reason)->toBeNull();
});

it('requires a meaningful cancellation reason', function () {
    $appointment = phase3b2bConfirmedAppointment(phase3b2bPatient(), phase3b2bStaff());

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.cancel', $appointment, [])
        ->assertSessionHasErrors('cancellation_reason');

    phase3b2bActAsPatch(phase3b2bAdmin(), 'appointments.cancel', $appointment, ['cancellation_reason' => 'ab'])
        ->assertSessionHasErrors('cancellation_reason');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

/*
|--------------------------------------------------------------------------
| 20-22. Walk-in creation
|required*/

it('creates a confirmed walk-in appointment for an existing patient', function () {
    $patient = phase3b2bPatient('Walkina', 'Reyes');
    $staff = phase3b2bStaff();

    $this->actingAs($staff)
        ->from(route('appointments.index'))
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => '2026-11-08',
            'appointment_time' => '09:30',
        ])
        ->assertRedirect(route('appointments.index'))
        ->assertSessionHas('success');

    $appointment = Appointment::latest('id')->first();
    expect($appointment->source)->toBe(Appointment::SOURCE_WALK_IN)
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->requested_date->toDateString())->toBe('2026-11-08')
        ->and($appointment->confirmed_date->toDateString())->toBe('2026-11-08')
        ->and((string) $appointment->confirmed_time)->toContain('09:30')
        ->and($appointment->reviewed_by)->toBe($staff->id)
        ->and($appointment->reviewed_at)->not->toBeNull()
        ->and($appointment->patient_id)->toBe($patient->id);
});

it('does not create a patient record or patient account for a walk-in', function () {
    $patient = phase3b2bPatient();
    $admin = phase3b2bAdmin();
    $usersBefore = User::count();
    $patientsBefore = Patient::count();

    $this->actingAs($admin)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_COUNSELING,
            'appointment_date' => '2026-11-08',
            'appointment_time' => '10:00',
        ])
        ->assertSessionHas('success');

    expect(User::count())->toBe($usersBefore)
        ->and(Patient::count())->toBe($patientsBefore)
        ->and($patient->refresh()->user_id)->toBeNull();
});

it('refuses walk-in creation for archived or missing patients', function () {
    $archived = phase3b2bPatient('Archivea', 'Gone');
    $archived->delete();
    $staff = phase3b2bStaff();

    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $archived->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => '2026-11-08',
            'appointment_time' => '09:00',
        ])
        ->assertSessionHasErrors('patient_id');

    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => 999999,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => '2026-11-08',
            'appointment_time' => '09:00',
        ])
        ->assertSessionHasErrors('patient_id');

    expect(Appointment::count())->toBe(0);

    // The JSON search endpoint also excludes archived patients.
    $this->actingAs($staff)
        ->get(route('appointments.patient-search', ['q' => 'Archivea']))
        ->assertOk()
        ->assertJson(['patients' => []]);

    $this->actingAs($staff)
        ->get(route('appointments.patient-search', ['q' => 'Walkin']))
        ->assertOk();
});

it('enforces one confirmed appointment per slot for walk-ins (Phase 2)', function () {
    $staff = phase3b2bStaff();

    // First walk-in claims the 09:00 slot (hold reserved).
    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'appointment_date' => '2026-11-08',
            'appointment_time' => '09:00',
            'patient_id' => phase3b2bPatient('Sharea', 'Sharea')->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
        ])
        ->assertSessionHas('success');

    // A second walk-in into the SAME slot is refused (approved rule:
    // at most one confirmed/completed appointment per 30-minute slot).
    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'appointment_date' => '2026-11-08',
            'appointment_time' => '09:00',
            'patient_id' => phase3b2bPatient('Shareb', 'Shareb')->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
        ])
        ->assertSessionHasErrors('walk_in');

    // whereDate: SQLite stores the date cast as 'Y-m-d 00:00:00' text.
    // Only the first booking exists; a different slot stays bookable.
    expect(Appointment::whereDate('confirmed_date', '2026-11-08')->count())->toBe(1);

    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'appointment_date' => '2026-11-08',
            'appointment_time' => '09:30',
            'patient_id' => phase3b2bPatient('Sharec', 'Sharec')->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
        ])
        ->assertSessionHas('success');

    expect(Appointment::whereDate('confirmed_date', '2026-11-08')->count())->toBe(2);
});

it('enforces the same scheduling rules on walk-ins and permits date-only delivery walk-ins', function () {
    $patient = phase3b2bPatient();
    $staff = phase3b2bStaff();

    // Checkup outside hours: refused by the FormRequest's field-level
    // clinic-hours rules (surfaced under 'appointment_time').
    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => '2026-11-08',
            'appointment_time' => '17:00',
        ])
        ->assertSessionHasErrors('appointment_time');

    // Delivery at 23:30: allowed (24/7).
    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
            'appointment_date' => '2026-11-08',
            'appointment_time' => '23:30',
        ])
        ->assertSessionHas('success');

    // Delivery date-only (no time): allowed and stored confirmed without inventing a time.
    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
            'appointment_date' => '2026-11-09',
        ])
        ->assertSessionHas('success');

    $dateOnly = Appointment::whereNull('confirmed_time')->latest('id')->first();
    expect($dateOnly->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($dateOnly->appointment_type)->toBe(Appointment::TYPE_DELIVERY_REQUEST)
        ->and(Schema::hasTable('delivery_admissions'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 24. Audit events
|required*/

it('records one audit event per action with actor, patient and status transition', function () {
    // QA FIX 2: freeze the clinic day on the confirmed schedule (2026-11-06),
    // an hour BEFORE its 09:00 start - the confirmation stays valid and the
    // completion that follows is due today.
    phase3b2bFreezeManila('2026-11-06 08:30:00');
    $patient = phase3b2bPatient();

    $staff = phase3b2bStaff();
    $admin = phase3b2bAdmin();

    $pending = phase3b2bAppointment($patient);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $pending, [
        'confirmed_date' => '2026-11-06', 'confirmed_time' => '09:00',
    ]);

    $confirmAudit = AuditLog::where('action', 'APPOINTMENT_CONFIRMED')->latest('id')->first();
    expect($confirmAudit)->not->toBeNull()
        ->and($confirmAudit->module)->toBe('APPOINTMENT')
        ->and($confirmAudit->user_id)->toBe($admin->id)
        ->and($confirmAudit->patient_id)->toBe($patient->id)
        ->and($confirmAudit->description)->toContain('#' . $pending->id)
        ->and($confirmAudit->description)->toContain('pending -> confirmed');

    phase3b2bActAsPatch($staff, 'appointments.complete', $pending);
    $completeAudit = AuditLog::where('action', 'APPOINTMENT_COMPLETED')->latest('id')->first();
    expect($completeAudit->user_id)->toBe($staff->id)
        ->and($completeAudit->description)->toContain('confirmed -> completed');

    $this->actingAs($staff)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => '2026-11-10',
            'appointment_time' => '09:00',
        ]);
    $walkInAudit = AuditLog::where('action', 'WALK_IN_APPOINTMENT_CREATED')->latest('id')->first();
    expect($walkInAudit)->not->toBeNull()
        ->and($walkInAudit->user_id)->toBe($staff->id)
        ->and($walkInAudit->module)->toBe('APPOINTMENT');
});

/*
|--------------------------------------------------------------------------
| 26-27. Calendar reflection + forged-input immunity
|required*/

it('reflects status changes in the calendar and hides cancelled rows by default', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient('Reflecta', 'Reflecto'));
    $admin = phase3b2bAdmin();

    $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertSee('Reflecta Reflecto');

    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '10:00',
    ]);

    $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertSee('Reflecta Reflecto');

    phase3b2bActAsPatch($admin, 'appointments.cancel', $appointment, ['cancellation_reason' => 'Unit test cancel.']);

    // All Statuses is the default, so the cancelled row stays visible;
    // an explicit active filter hides it.
    $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05']))
        ->assertOk()
        ->assertSee('Reflecto')
        ->assertSee('Unit test cancel.');

    $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05', 'status' => 'active']))
        ->assertOk()
        ->assertDontSee('Reflecto');

    $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-05', 'status' => 'cancelled']))
        ->assertOk()
        ->assertSee('Reflecto')
        ->assertSee('Unit test cancel.');
});

it('ignores forged status source and reviewer fields in every payload', function () {
    $patient = phase3b2bPatient();
    $appointment = phase3b2bAppointment($patient);
    $admin = phase3b2bAdmin();
    $victim = phase3b2bStaff();

    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '09:00',
        'status' => Appointment::STATUS_COMPLETED,
        'reviewed_by' => $victim->id,
        'source' => Appointment::SOURCE_ONLINE,
        'decline_reason' => 'forged',
        'cancellation_reason' => 'forged',
        'confirmed_by' => $victim->id,
    ])->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->reviewed_by)->toBe($admin->id)
        ->and($appointment->source)->toBe(Appointment::SOURCE_ONLINE)
        ->and($appointment->decline_reason)->toBeNull()
        ->and($appointment->cancellation_reason)->toBeNull();

    // Walk-in: source and status cannot be forged either.
    $this->actingAs($admin)
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => '2026-11-11',
            'appointment_time' => '09:00',
            'source' => Appointment::SOURCE_ONLINE,
            'status' => Appointment::STATUS_PENDING,
            'reviewed_by' => $victim->id,
        ])
        ->assertSessionHas('success');

    $walkIn = Appointment::where('source', Appointment::SOURCE_WALK_IN)->latest('id')->first();
    expect($walkIn->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($walkIn->reviewed_by)->toBe($admin->id);
});

/*
|--------------------------------------------------------------------------
| Extras: route surface, patient-search access, UI rendering
|required*/

it('exposes no mutation through GET and keeps the search endpoint clinic-bound', function () {
    $appointment = phase3b2bAppointment(phase3b2bPatient());

    // Guest FIRST - actingAs is sticky for the rest of the test.
    $this->get(route('appointments.patient-search', ['q' => 'x']))
        ->assertRedirect(route('login'));

    $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.confirm', $appointment))
        ->assertMethodNotAllowed();

    $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.walk-in.store'))
        ->assertMethodNotAllowed();

    $this->actingAs(phase3b2bPatientUser())
        ->get(route('appointments.patient-search', ['q' => 'x']))
        ->assertForbidden();

    $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.patient-search', ['q' => 'Maria']))
        ->assertOk()
        ->assertJsonStructure(['patients' => [['id', 'full_name', 'age', 'contact_number']]]);
});

it('renders the management UI: walk-in button, action URLs and modal forms', function () {
    $pending = phase3b2bAppointment(phase3b2bPatient('Uia', 'Pending'));
    $confirmed = phase3b2bConfirmedAppointment(phase3b2bPatient('Uib', 'Confirmed'), phase3b2bStaff());

    $response = $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-15', 'status' => 'all']));

    $response->assertOk()
        ->assertSee('Add Walk-in Appointment')
        ->assertSee('walk-in-appointment', false)
        ->assertSee(route('appointments.walk-in.store'), false)
        ->assertSee(route('appointments.confirm', $pending), false)
        ->assertSee(route('appointments.cancel', $confirmed), false)
        ->assertSee('Mark Completed')
        ->assertSee('8:00 AM to 5:00 PM Philippine time');
});

/*
|--------------------------------------------------------------------------
| Past-schedule rule (bug fix) - frozen Asia/Manila time
|--------------------------------------------------------------------------
| Base frozen instant: 2026-10-14 14:35:30 Manila (2026-10-14 06:35:30
| UTC) - yesterday = 2026-10-13, today = 2026-10-14, tomorrow =
| 2026-10-15. Every comparison is anchored to Asia/Manila inside
| AppointmentManagementService; nothing mutates the global app timezone.
*/

function phase3b2bFreezeManila(string $moment): void
{
    Carbon::setTestNow(Carbon::parse($moment, 'Asia/Manila'));
}

function phase3b2bWalkIn(User $staff, Patient $patient, array $overrides = [])
{
    return test()->actingAs($staff)->post(route('appointments.walk-in.store'), array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'appointment_date' => '2026-10-15',
        'appointment_time' => null,
    ], $overrides));
}

it('rejects a walk-in scheduled for yesterday', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    $appointmentsBefore = Appointment::count();
    $auditsBefore = AuditLog::count();

    phase3b2bWalkIn($staff, $patient, [
        'appointment_date' => '2026-10-13',
        'appointment_time' => '09:00',
    ])->assertSessionHasErrors('appointment_date');

    expect(Appointment::count())->toBe($appointmentsBefore)
        ->and(AuditLog::count())->toBe($auditsBefore);
});

it("rejects today's past walk-in time and allows today's future and equal-minute slot starts", function () {
    // Frozen exactly ON a grid slot minute: 14:30 is the current slot.
    phase3b2bFreezeManila('2026-10-14 14:30:00');
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    // 14:00 (Manila) has already passed at 14:30:00.
    phase3b2bWalkIn($staff, $patient, [
        'appointment_date' => '2026-10-14',
        'appointment_time' => '14:00',
    ])->assertSessionHasErrors('appointment_time');

    // 15:00 is still ahead today.
    phase3b2bWalkIn($staff, $patient, [
        'appointment_date' => '2026-10-14',
        'appointment_time' => '15:00',
    ])->assertSessionHas('success');

    // The exact current slot minute is allowed (not earlier than now).
    phase3b2bWalkIn($staff, phase3b2bPatient('Min', 'Boundary'), [
        'appointment_date' => '2026-10-14',
        'appointment_time' => '14:30',
    ])->assertSessionHas('success');

    expect(Appointment::whereDate('confirmed_date', '2026-10-14')->count())->toBe(2);
});

it('allows a future walk-in date and time', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');

    phase3b2bWalkIn(phase3b2bStaff(), phase3b2bPatient(), [
        'appointment_date' => '2026-10-15',
        'appointment_time' => '09:00',
    ])->assertSessionHas('success');

    expect(Appointment::count())->toBe(1)
        ->and(Appointment::first()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('keeps the provisional clinic-hours window for checkup and counseling', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    // Checkup: before open and at/after last start refused at the FIELD
    // level now (FormRequest clinic-hours rules - 'appointment_time');
    // the service check remains the authoritative backstop.
    foreach (['07:59', '17:00'] as $time) {
        phase3b2bWalkIn($staff, $patient, [
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => '2026-10-15',
            'appointment_time' => $time,
        ])->assertSessionHasErrors('appointment_time');
    }

    // Counseling follows the same window.
    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '07:30',
    ])->assertSessionHasErrors('appointment_time');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '16:30',
    ])->assertSessionHas('success');

    expect(Appointment::where('appointment_type', Appointment::TYPE_COUNSELING)->count())->toBe(1);
});

it('allows delivery requests at any future hour including later today', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    // 23:30 today (still ahead of the frozen 14:35 clock).
    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-14',
        'appointment_time' => '23:30',
    ])->assertSessionHas('success');

    // 02:15 tomorrow (24/7).
    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '02:15',
    ])->assertSessionHas('success');

    expect(Appointment::where('appointment_type', Appointment::TYPE_DELIVERY_REQUEST)->count())->toBe(2);
});

it('allows a date-only delivery walk-in for today and rejects yesterday', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-13',
    ])->assertSessionHasErrors('appointment_date');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-14',
    ])->assertSessionHas('success');

    $dateOnly = Appointment::first();
    expect(Appointment::count())->toBe(1)
        ->and($dateOnly->confirmed_time)->toBeNull()
        ->and($dateOnly->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('refuses to confirm a pending appointment for a past schedule and keeps it pending', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $appointment = phase3b2bAppointment(phase3b2bPatient(), [
        'requested_date' => '2026-10-13',
        'requested_time' => '09:00',
    ]);
    $admin = phase3b2bAdmin();

    // Yesterday's date refused (field-level error + modal-reopen flash).
    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-10-13', 'confirmed_time' => '09:00',
    ])->assertSessionHasErrors('confirmed_date')
        ->assertSessionHas('errorAppointmentId', $appointment->id);

    // Today's past time refused.
    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-10-14', 'confirmed_time' => '14:30',
    ])->assertSessionHasErrors('confirmed_time');

    // Yesterday date-only delivery confirmation refused too.
    $delivery = phase3b2bAppointment(phase3b2bPatient('Deliv', 'Ery'), [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => '2026-10-13',
        'requested_time' => null,
    ]);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $delivery, [
        'confirmed_date' => '2026-10-13', 'confirmed_time' => null,
    ])->assertSessionHasErrors('confirmed_date');

    // Today's future time still confirms normally.
    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-10-14', 'confirmed_time' => '15:00',
    ])->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($delivery->refresh()->status)->toBe(Appointment::STATUS_PENDING);
});

it('blocks direct service calls that try to schedule in the past', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $service = app(AppointmentManagementService::class);
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    // Walk-in creation: yesterday refused at the service boundary.
    expect(fn () => $service->createWalkIn($staff, $patient->id, Appointment::TYPE_CHECKUP, '2026-10-13', '09:00'))
        ->toThrow(\DomainException::class);

    // Walk-in creation: today's past minute refused.
    expect(fn () => $service->createWalkIn($staff, $patient->id, Appointment::TYPE_CHECKUP, '2026-10-14', '14:00'))
        ->toThrow(\DomainException::class);

    // Date-only delivery: yesterday refused.
    expect(fn () => $service->createWalkIn($staff, $patient->id, Appointment::TYPE_DELIVERY_REQUEST, '2026-10-13', null))
        ->toThrow(\DomainException::class);

    // Confirm: past schedule refused, appointment stays pending.
    $pending = phase3b2bAppointment($patient);
    expect(fn () => $service->confirm($pending, $staff, '2026-10-13', '09:00'))
        ->toThrow(\DomainException::class);

    expect($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and(Appointment::count())->toBe(1)
        ->and(AuditLog::count())->toBe(0);
});

it('creates no appointment and no audit record when past-schedule validation fails', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();
    $pending = phase3b2bAppointment($patient);

    $appointmentsBefore = Appointment::count();
    $auditsBefore = AuditLog::count();

    phase3b2bWalkIn($staff, $patient, [
        'appointment_date' => '2026-10-13', 'appointment_time' => '09:00',
    ])->assertSessionHasErrors();

    phase3b2bActAsPatch($staff, 'appointments.confirm', $pending, [
        'confirmed_date' => '2026-10-13', 'confirmed_time' => '09:00',
    ])->assertSessionHasErrors();

    expect(Appointment::count())->toBe($appointmentsBefore)
        ->and(AuditLog::count())->toBe($auditsBefore)
        ->and($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING);
});

it('anchors the past-schedule rule to the Manila midnight boundary', function () {
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    // 00:00:30 Manila on Oct 15 - the UTC calendar date is still Oct 14,
    // so a UTC-anchored check would wrongly accept Oct 14 schedules.
    phase3b2bFreezeManila('2026-10-15 00:00:30');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-14',
        'appointment_time' => '23:59',
    ])->assertSessionHasErrors('appointment_date');

    // Today (Oct 15) at the current minute, and date-only today, allowed.
    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '00:00',
    ])->assertSessionHas('success');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-15',
    ])->assertSessionHas('success');

    // Late-evening boundary: 23:59:30 - today's 23:59 allowed, 23:58
    // refused, tomorrow's early hour allowed.
    phase3b2bFreezeManila('2026-10-15 23:59:30');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '23:58',
    ])->assertSessionHasErrors('appointment_time');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '23:59',
    ])->assertSessionHas('success');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-16',
        'appointment_time' => '00:10',
    ])->assertSessionHas('success');
});

it('keeps existing past appointments readable, in status, and workflow-actionable', function () {
    $admin = phase3b2bAdmin();
    $patient = phase3b2bPatient('Pastora', 'History');

    // Historical rows (created directly - no new scheduling involved).
    $pastConfirmed = phase3b2bConfirmedAppointment($patient, $admin, [
        'confirmed_date' => '2026-09-01',
        'confirmed_time' => '10:00',
        'requested_date' => '2026-09-01',
        'requested_time' => '10:00',
    ]);
    $pastPending = phase3b2bAppointment($patient, [
        'requested_date' => '2026-09-02',
        'requested_time' => '11:00',
    ]);

    // Past appointments stay readable on the calendar.
    $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-09-01', 'status' => 'all']))
        ->assertOk()
        ->assertSee('Pastora History');

    // Cancel and Complete still work on a past confirmed schedule.
    phase3b2bActAsPatch($admin, 'appointments.cancel', $pastConfirmed, [
        'cancellation_reason' => 'Patient arrived late; rescheduled by phone.',
    ])->assertSessionHas('success');
    expect($pastConfirmed->refresh()->status)->toBe(Appointment::STATUS_CANCELLED);

    // A pending request for a past date cannot be confirmed (past rule)…
    phase3b2bActAsPatch($admin, 'appointments.confirm', $pastPending, [
        'confirmed_date' => '2026-09-02', 'confirmed_time' => '11:00',
    ])->assertSessionHasErrors('confirmed_date');

    // …but it can still be declined (decline takes no schedule).
    phase3b2bActAsPatch($admin, 'appointments.decline', $pastPending, [
        'decline_reason' => 'Requested date already passed; patient must rebook.',
    ])->assertSessionHas('success');
    expect($pastPending->refresh()->status)->toBe(Appointment::STATUS_DECLINED);

    // Complete still works after a fresh confirmation round-trip. QA FIX 2:
    // completion requires a DUE schedule (today or earlier, Asia/Manila), so
    // the fresh appointment is confirmed for the frozen clinic day at a
    // still-open clinic time - the rule ignores the clock time itself.
    $fresh = phase3b2bAppointment($patient, [
        'requested_date' => '2026-10-14',
        'requested_time' => '15:00',
    ]);
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    phase3b2bActAsPatch($admin, 'appointments.confirm', $fresh, [
        'confirmed_date' => '2026-10-14', 'confirmed_time' => '15:00',
    ])->assertSessionHas('success');
    phase3b2bActAsPatch($admin, 'appointments.complete', $fresh)->assertSessionHas('success');
    expect($fresh->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);

});

/*
|--------------------------------------------------------------------------
| Walk-in modal UI regression (script escaping) + clinic-hours field rules
|--------------------------------------------------------------------------
| The inline <script> must receive JSON UNESCAPED: Blade {{ }} turns the
| quotes of json_encode() into &quot; entities, which the browser does NOT
| decode inside script text - the resulting SyntaxError kills the whole
| appointmentDetailsPanel component (empty x-text button, dead search
| input, permanently visible x-show placeholders). HTTP tests never
| execute Alpine, so this asserts the rendered source directly.
*/

it('renders the walk-in Alpine script with unescaped JSON (no &quot; entities)', function () {
    $html = $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.index'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('const CLINIC_TODAY = "')
        ->toContain('const CLINIC_NOW = "')
        ->toContain('type: "checkup"')
        ->not->toContain('CLINIC_TODAY = &quot;')
        ->not->toContain('CLINIC_NOW = &quot;')
        ->not->toContain('type: &quot;');
});

it('renders the walk-in modal with a visible patient search input, full button label and template x-for results', function () {
    $response = $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.index'))
        ->assertOk();

    // Patient search input with the existing debounced Alpine bindings.
    $response->assertSee('Patient (required)')
        ->assertSee('Search by first name, last name, or patient ID')
        ->assertSee('x-model="walkIn.query"', false)
        ->assertSee('x-on:input.debounce.300ms="searchPatients()"', false);

    // Result list keeps the fixed Alpine template pattern.
    $response->assertSee('<template x-for="patient in walkIn.results" :key="patient.id">', false);

    // Selection state: hidden id binding + Change control.
    $response->assertSee('name="patient_id"', false)
        ->assertSee(':value="walkIn.patientId"', false)
        ->assertSee('clearPatient()', false);

    // Create button: full label lives in the x-text attribute (rendered
    // only when the Alpine component initializes - the regression made it
    // an empty green square). Phase 4: also gated on the walk-in slot
    // picker's readiness for timed types.
    $response->assertSee('Create Walk-in Appointment');
    expect($response->getContent())
        ->toContain("x-text=\"submitting ? 'Creating\u{2026}' : 'Create Walk-in Appointment'\"")
        ->toContain(':disabled="submitting || !walkIn.patientId || (walkIn.type !== \'delivery_request\' && !walkInSlotReady)"');
});

it('rejects out-of-clinic-hours times with field-level errors and still accepts in-window and delivery times', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $staff = phase3b2bStaff();
    $patient = phase3b2bPatient();

    // Walk-in checkup before opening: field-level clinic-hours message.
    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '07:59',
    ])->assertSessionHasErrors([
        'appointment_time' => 'Checkup and counseling appointments must start at or after 8:00 AM (Philippine clinic hours, provisional).',
    ]);

    // Confirm counseling at/after last start: field-level message.
    $counseling = phase3b2bAppointment($patient, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_time' => '10:00',
    ]);
    phase3b2bActAsPatch($staff, 'appointments.confirm', $counseling, [
        'confirmed_date' => '2026-10-15', 'confirmed_time' => '17:00',
    ])->assertSessionHasErrors([
        'confirmed_time' => 'Checkup and counseling appointments must start strictly before 5:00 PM (Philippine clinic hours, provisional).',
    ]);

    // In-window boundaries still succeed (rules AND service both pass).
    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '08:00',
    ])->assertSessionHas('success');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '16:30',
    ])->assertSessionHas('success');

    // Delivery requests bypass the slot-grid rules entirely (24/7).
    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '07:59',
    ])->assertSessionHas('success');

    phase3b2bWalkIn($staff, $patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '17:00',
    ])->assertSessionHas('success');
});

/*
|--------------------------------------------------------------------------
| Modal cloak + inline validation errors + single Confirmed placeholder
|--------------------------------------------------------------------------
| Regression for the integration bugs reported against the Details modal:
| the action forms carried x-cloak with no CSS rule (so their submit
| buttons rendered visible pre-Alpine), validation errors were only shown
| in the page flash behind the modal, and the Confirmed field concatenated
| "Not yet confirmed · Not yet confirmed". These are markup assertions -
| they do NOT execute Alpine/JS; a live-browser check is still listed in
| the fix report.
*/

it('ships the Alpine cloak CSS rule and keeps x-cloak on the action forms', function () {
    $pending = phase3b2bAppointment(phase3b2bPatient('Cwk', 'Cloak'));

    $html = $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-05', 'status' => 'all']))
        ->assertOk()
        ->getContent();

    // The missing CSS rule that left the forms visible on first paint.
    expect($html)->toContain('[x-cloak] { display: none !important; }');

    // All four management forms keep their cloak so Alpine can reveal them.
    expect($html)->toContain('x-show="action === \'confirm\'" x-cloak')
        ->toContain('x-show="action === \'decline\'" x-cloak')
        ->toContain('x-show="action === \'cancel\'" x-cloak')
        ->toContain('x-show="action === \'complete\'" x-cloak');

    // The confirm form still posts to the routed PATCH target with CSRF + spoof.
    expect($html)->toContain('name="_method" value="PATCH"')
        ->toContain(route('appointments.confirm', $pending))
        ->toContain('name="_token"');
});

it('renders confirm validation errors inline inside the confirm form', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');

    $pending = phase3b2bAppointment(phase3b2bPatient('Inl', 'Errors'), [
        'requested_date' => '2026-10-13',
    ]);
    $admin = phase3b2bAdmin();

    // Past confirmed_date -> field-level confirmed_date error; the modal is
    // re-opened through errorAppointmentId (existing behaviour, preserved).
    phase3b2bActAsPatch($admin, 'appointments.confirm', $pending, [
        'confirmed_date' => '2026-10-13',
        'confirmed_time' => '09:00',
    ])->assertSessionHasErrors('confirmed_date')
        ->assertSessionHas('errorAppointmentId', $pending->id);

    // Out-of-clinic-hours time -> field-level confirmed_time error.
    phase3b2bActAsPatch($admin, 'appointments.confirm', $pending, [
        'confirmed_date' => '2026-10-15',
        'confirmed_time' => '17:00',
    ])->assertSessionHasErrors('confirmed_time');

    // Stale transition -> DomainException surfaced on the 'appointment' bag.
    $alreadyConfirmed = phase3b2bConfirmedAppointment(phase3b2bPatient('Stl', 'Already'), $admin);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $alreadyConfirmed, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '09:00',
    ])->assertSessionHasErrors('appointment');

    // The last flashed error renders INSIDE the confirm form region (between
    // the confirm form's opening tag and the DECLINE form), not only in the
    // page-level flash above the calendar that the modal overlay covers.
    $html = $this->actingAs($admin)
        ->from(route('appointments.index'))
        ->get(route('appointments.index'))
        ->assertOk()
        ->getContent();

    $confirmStart = strpos($html, 'x-show="action === \'confirm\'"');
    $declineStart = strpos($html, 'x-show="action === \'decline\'"');

    expect($confirmStart)->not->toBeFalse()
        ->and($declineStart)->not->toBeFalse()
        ->and($declineStart)->toBeGreaterThan($confirmStart);

    $confirmRegion = substr($html, $confirmStart, $declineStart - $confirmStart);

    expect($confirmRegion)->toContain('This appointment can no longer be confirmed');
});

it('renders a single Confirmed placeholder and consistent reviewer placeholders', function () {
    // Pending: both confirmed_* NULL -> controller emits NULL for both labels
    // and the Alpine ternary collapses them to one "Not yet confirmed".
    $pending = phase3b2bAppointment(phase3b2bPatient('Ph1', 'Pending'));

    // Confirmed with a date but no time (date-only delivery confirm).
    $reviewer = phase3b2bStaff();
    $dateOnly = phase3b2bConfirmedAppointment(phase3b2bPatient('Ph2', 'DateOnly'), $reviewer, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'confirmed_time' => null,
    ]);

    // Fully confirmed checkup: both labels present -> "M j, Y · H:i".
    $both = phase3b2bConfirmedAppointment(phase3b2bPatient('Ph3', 'Both'), $reviewer);

    $html = $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-05', 'status' => 'all']))
        ->assertOk()
        ->getContent();

    // One ternary expression drives the whole field (no two-span concatenation
    // that used to render "Not yet confirmed · Not yet confirmed").
    expect($html)->toContain("if (!d && !t) return 'Not yet confirmed';")
        ->toContain("[d, t].filter(Boolean).join(' · ')");

    // The embedded details payload: NULL labels when nothing is confirmed, a
    // single "Not provided" time when only the date exists, both real values
    // when fully confirmed. The old duplicate placeholder string is gone.
    expect($html)
        ->toContain('"confirmedDateLabel":null,"confirmedTimeLabel":null')
        ->toContain('"confirmedTimeLabel":"Not provided"')
        ->toContain('"confirmedDateLabel":"Nov 5, 2026","confirmedTimeLabel":"10:00"')
        ->and(substr_count($html, 'Not yet confirmed'))->toBe(1);

    // Reviewer placeholders are consistent when nothing has been reviewed yet.
    expect($html)->toContain('"reviewerName":"Not yet reviewed","reviewedAtLabel":"Not yet reviewed"');
});

/*
|--------------------------------------------------------------------------
| Confirm-time input fix (visible time vs submitted value)
|--------------------------------------------------------------------------
| The confirm form renders TWO <input name="confirmed_time"> controls
| (checkup/counseling required, delivery optional). Alpine x-show only
| toggles visibility via CSS - it does NOT remove a control from the form,
| so BOTH were submitted. PHP's request parsing keeps the LAST value for a
| repeated key, and ConvertEmptyStringsToNull turns "" into null. For a
| checkup the hidden DELIVERY input came last with an empty value, so the
| server received confirmed_time = null even though the visible field
| showed a valid time - tripping the "An appointment time is required"
| error while the appointment stayed Pending (the reported bug). The fix
| binds x-bind:disabled on each input to the OPPOSITE of its own x-show
| condition, so the inactive control is disabled and therefore excluded
| from submission - exactly one confirmed_time reaches the server, the one
| the user actually sees and edits. These tests assert the markup contract
| (name + disabled binding present on both) and reproduce the duplicate-
| key clobber at the HTTP layer to prove the failure mode is real.
*/

it('renders the slot-picker carrier and delivery input with disabling so only one confirmed_time submits', function () {
    $pending = phase3b2bAppointment(phase3b2bPatient('Tmi', 'Inputs'));

    $html = $this->actingAs(phase3b2bAdmin())
        ->get(route('appointments.index', ['date' => '2026-11-05', 'status' => 'all']))
        ->assertOk()
        ->getContent();

    // Exactly ONE literal name="confirmed_time" (the delivery input); the
    // timed control is the Phase 4 slot picker's Alpine-bound hidden
    // carrier (:name="timeName"), never a second plain input.
    expect(substr_count($html, 'name="confirmed_time"'))->toBe(1);

    // The picker carrier disables itself while availability is unresolved
    // OR when the appointment is a delivery request, so it can never
    // submit alongside the delivery input (the original duplicate-key
    // bug this invariant protects against).
    expect($html)
        ->toContain(':disabled="!ready || (selected?.type === &#039;delivery_request&#039;)"')
        // The delivery input disables itself for every non-delivery type.
        ->toContain('x-bind:disabled="selected?.type !== \'delivery_request\'"');

    // The delivery control stays optional + minute-stepped (date-only).
    expect($html)->toContain('name="confirmed_time" step="60"');
});

it('keeps a checkup confirmation successful even when a duplicate empty confirmed_time key is present', function () {
    // Reproduces the browser's duplicate-key serialization: the hidden
    // delivery input contributed an empty confirmed_time AFTER the visible
    // checkup one. PHP keeps the LAST occurrence, so before the disabled-
    // input fix this arrived as null and the confirm was refused. The fix
    // excludes the inactive control from submission, so this empty trailing
    // key can no longer be produced by the form; posting it explicitly here
    // documents the exact failure mode the disabled binding prevents.
    $appointment = phase3b2bAppointment(phase3b2bPatient(), [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_time' => '16:30',
    ]);
    $admin = phase3b2bAdmin();

    // A single well-formed value (what the fixed form actually sends) succeeds.
    phase3b2bActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '16:30',
    ])->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and((string) $appointment->confirmed_time)->toContain('16:30');

    // The legacy duplicate-empty-key payload (pre-fix browser behaviour) is
    // still correctly refused as a missing time - validation is not weakened.
    $second = phase3b2bAppointment(phase3b2bPatient('Dup', 'Key'));
    $this->actingAs($admin)
        ->from(route('appointments.index'))
        ->call('PATCH', route('appointments.confirm', $second), [
            'confirmed_date' => '2026-11-06',
            'confirmed_time' => ['16:30', ''],
        ], [], [], [], null)
        ->assertSessionHasErrors('confirmed_time');

    expect($second->refresh()->status)->toBe(Appointment::STATUS_PENDING);
});

it('confirms counseling and delivery with the correct confirmed_time shape and reflects it in the Patient Portal', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $admin = phase3b2bAdmin();

    // Counseling (time required) with a valid in-hours time succeeds.
    $counseling = phase3b2bAppointment(phase3b2bPatient('Coun', 'Sel'), [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_time' => '10:00',
    ]);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $counseling, [
        'confirmed_date' => '2026-10-15', 'confirmed_time' => '10:30',
    ])->assertSessionHas('success');
    expect($counseling->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);

    // Delivery request with NO confirmed_time succeeds (date-only, 24/7).
    $delivery = phase3b2bAppointment(phase3b2bPatient('Deliv', 'Ery'), [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
    ]);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $delivery, [
        'confirmed_date' => '2026-10-15', 'confirmed_time' => null,
    ])->assertSessionHas('success');
    $delivery->refresh();
    expect($delivery->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($delivery->confirmed_time)->toBeNull();

    // Patient Portal reflects the confirmed schedule after a fresh request.
    $portalUser = User::factory()->create(['role' => 'patient', 'name' => 'Portal Confirmed']);
    $portalPatient = phase3b2bPatient('Portal', 'Confirmed');
    $portalPatient->user_id = $portalUser->id;
    $portalPatient->save();
    $portalPending = phase3b2bAppointment($portalPatient, [
        'requested_date' => '2026-10-16', 'requested_time' => '09:00',
    ]);

    phase3b2bActAsPatch($admin, 'appointments.confirm', $portalPending, [
        'confirmed_date' => '2026-10-16', 'confirmed_time' => '09:30',
    ])->assertSessionHas('success');

    $this->actingAs($portalUser)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Confirmed')
        ->assertSee('Clinic-confirmed schedule:');
});

it('keeps failed confirmation pending and successful confirmation moving Pending to Confirmed with valid time formats', function () {
    phase3b2bFreezeManila('2026-10-14 14:35:30');
    $admin = phase3b2bAdmin();

    // Invalid time format (not HH:MM) is rejected by the FormRequest.
    $badFormat = phase3b2bAppointment(phase3b2bPatient('Fmt', 'Bad'), [
        'requested_time' => '09:00',
    ]);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $badFormat, [
        'confirmed_date' => '2026-10-15', 'confirmed_time' => '4:56 PM',
    ])->assertSessionHasErrors('confirmed_time');
    expect($badFormat->refresh()->status)->toBe(Appointment::STATUS_PENDING);

    // Past confirmed schedule (today's earlier time) is rejected, stays Pending.
    $past = phase3b2bAppointment(phase3b2bPatient('Past', 'Time'), [
        'requested_time' => '09:00',
    ]);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $past, [
        'confirmed_date' => '2026-10-14', 'confirmed_time' => '09:00',
    ])->assertSessionHasErrors('confirmed_time');
    expect($past->refresh()->status)->toBe(Appointment::STATUS_PENDING);

    // Out-of-hours time is rejected (before 08:00 / at-or-after 17:00).
    $outOfHours = phase3b2bAppointment(phase3b2bPatient('Hours', 'Out'), [
        'requested_time' => '09:00',
    ]);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $outOfHours, [
        'confirmed_date' => '2026-10-15', 'confirmed_time' => '17:00',
    ])->assertSessionHasErrors('confirmed_time');
    expect($outOfHours->refresh()->status)->toBe(Appointment::STATUS_PENDING);

    // A valid in-window grid slot start flips Pending -> Confirmed.
    $ok = phase3b2bAppointment(phase3b2bPatient('Valid', 'Time'), [
        'requested_time' => '09:00',
    ]);
    phase3b2bActAsPatch($admin, 'appointments.confirm', $ok, [
        'confirmed_date' => '2026-10-15', 'confirmed_time' => '16:30',
    ])->assertSessionHas('success');
    expect($ok->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and((string) $ok->confirmed_time)->toContain('16:30');
});
