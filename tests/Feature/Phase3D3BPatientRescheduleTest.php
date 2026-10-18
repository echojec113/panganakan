<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentRescheduleRequestedNotification;
use App\Services\AppointmentManagementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3D.3B Step 1 - Patient Portal reschedule REQUEST submission
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Backend-only slice: the
| owning patient proposes a new schedule for their own CONFIRMED ONLINE
| appointment and ONE pending appointment_change_requests row
| (type=reschedule) is created. Covered: submission atomicity (request
| row + PATIENT_REQUESTED_RESCHEDULE audit, appointment byte-identical,
| K3 clinic submission notification dispatched post-commit to active
| Admin/Staff only, never the patient; optional trimmed reason with
| length limits;
| proposed_date format/requirement; type-conditional proposed_time
| (required in clinic hours for checkup/counseling, date-only for
| delivery per decision K5 - a submitted time is ignored); the shared
| Manila past-schedule rule (past date/time refused, equal minute
| allowed, date-only through the end of its scheduled day); collision
| guard 1 (identical to the current confirmed schedule refused,
| approved decision K2) and guard 2 (exact duplicate of ANOTHER active
| same-patient same-type appointment refused, different type allowed,
| self excluded); the cross-type one-pending invariant in BOTH
| directions; the current-schedule cutoff; walk-in/status/foreign
| ownership refusals; the guest/clinic/unlinked/archived matrix;
| forged input ignored; sequential double submit refused; a
| service-direct ownership refusal; and the schema contract (no
| migrations, no new tables or columns beyond Phase 3D.2A).
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3d3User(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d3Patient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 29,
        'email' => "phase3d3{$sequence}@portal.test",
        'contact_number' => '09179876543',
        'address_line' => '12 Bonifacio Street',
        'barangay' => 'San Isidro',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d3Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3d3Eligible(): array
{
    $user = phase3d3User('patient');
    $patient = phase3d3Patient();
    phase3d3Link($patient, $user);

    return [$user, $patient];
}

function phase3d3ConfirmedAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
        'confirmed_date' => '2026-11-07',
        'confirmed_time' => '14:00',
        'status' => Appointment::STATUS_CONFIRMED,
        'reviewed_by' => phase3d3User('staff')->id,
        'reviewed_at' => now(),
    ], $overrides));
}

function phase3d3DeliveryAppointment(Patient $patient, array $overrides = []): Appointment
{
    return phase3d3ConfirmedAppointment($patient, array_merge([
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
        'confirmed_date' => '2026-11-10',
        'confirmed_time' => null,
    ], $overrides));
}

function phase3d3PendingAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-12-02',
        'requested_time' => '09:00',
        'status' => Appointment::STATUS_PENDING,
    ], $overrides));
}

function phase3d3ReschedulePost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)->post(
        route('patient.appointments.rescheduleRequest', ['appointmentId' => $appointment->id]),
        $data
    );
}

function phase3d3CancelRequestPost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)->post(
        route('patient.appointments.cancellationRequest', ['appointmentId' => $appointment->id]),
        $data
    );
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3d3Freeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

function phase3d3NotificationCount(): int
{
    return DB::table('notifications')->count();
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Submission atomicity
|--------------------------------------------------------------------------
*/

it('creates a pending reschedule request and keeps the confirmed appointment untouched', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);
    $originalReviewer = $appointment->reviewed_by;
    $originalUpdatedAt = $appointment->updated_at?->toDateTimeString();

    $response = phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
        'reason' => 'Work shift changed, need a later slot.',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertSessionHas('success')
        ->assertRedirect(route('patient.appointments.index'));

    $request = AppointmentChangeRequest::sole();

    expect($request->appointment_id)->toBe($appointment->id)
        ->and($request->type)->toBe(AppointmentChangeRequest::TYPE_RESCHEDULE)
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($request->proposed_date?->toDateString())->toBe('2026-11-20')
        ->and($request->proposed_time)->toBe('10:00')
        ->and($request->reason)->toBe('Work shift changed, need a later slot.')
        ->and($request->reviewed_by)->toBeNull()
        ->and($request->reviewed_at)->toBeNull()
        ->and($request->review_note)->toBeNull();

    $appointment->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->appointment_type)->toBe(Appointment::TYPE_CHECKUP)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($appointment->reviewed_by)->toBe($originalReviewer)
        ->and($appointment->updated_at?->toDateTimeString())->toBe($originalUpdatedAt)
        ->and($appointment->cancellation_reason)->toBeNull()
        ->and($appointment->decline_reason)->toBeNull();

    $audit = AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE');

    expect($audit->count())->toBe(1)
        ->and($audit->first()->patient_id)->toBe($patient->id)
        ->and(phase3d3NotificationCount())->toBe(1);
});

it('dispatches the K3 clinic submission notification to active staff after commit and never notifies the patient', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);
    $clinicStaff = User::query()->findOrFail($appointment->reviewed_by);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-21',
        'proposed_time' => '11:00',
        'reason' => 'Another slot works for me.',
    ])->assertSessionHasNoErrors();

    $changeRequest = AppointmentChangeRequest::sole();
    $rows = DB::table('notifications')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->type)->toBe(AppointmentRescheduleRequestedNotification::class)
        ->and($rows[0]->notifiable_id)->toBe($clinicStaff->id)
        ->and($rows[0]->read_at)->toBeNull()
        ->and(json_decode($rows[0]->data, true))
            ->toMatchArray([
                'type' => 'APPOINTMENT_RESCHEDULE_REQUESTED',
                'title' => 'Reschedule Request Submitted',
                'message' => 'A patient has requested to reschedule an appointment.',
                'event_key' => 'appointment-reschedule-requested:' . $changeRequest->id,
            ])
        // The reason stays out of the shared notification payload.
        ->and(json_decode($rows[0]->data, true)['message'])
            ->not->toContain('Another slot works')
        // The submitting patient never receives a notification for
        // their own submission.
        ->and($user->notifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Reason (optional, trimmed, 3-1000)
|--------------------------------------------------------------------------
*/

it('treats the reason as optional and trims a supplied one', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $first = phase3d3ConfirmedAppointment($patient);
    $second = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $first, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasNoErrors();

    phase3d3ReschedulePost($user, $second, [
        'proposed_date' => '2026-11-21',
        'proposed_time' => '10:00',
        'reason' => '   Another slot works for me.   ',
    ])->assertSessionHasNoErrors();

    $requests = AppointmentChangeRequest::orderBy('id')->get();

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->reason)->toBeNull()
        ->and($requests[1]->reason)->toBe('Another slot works for me.');
});

it('validates the optional reason length', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
        'reason' => 'ab',
    ])->assertSessionHasErrors('reason');

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
        'reason' => str_repeat('x', 1001),
    ])->assertSessionHasErrors('reason');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Proposed schedule validation (format, clinic hours, Manila past rule)
|--------------------------------------------------------------------------
*/

it('requires a well-formed proposed date', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('proposed_date');

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '20/11/2026',
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('proposed_date');

    expect(AppointmentChangeRequest::count())->toBe(0);
});

it('requires a proposed time inside clinic hours for checkup appointments', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
    ])->assertSessionHasErrors('proposed_time');

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '07:00',
    ])->assertSessionHasErrors('proposed_time');

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '17:00',
    ])->assertSessionHasErrors('proposed_time');

    expect(AppointmentChangeRequest::count())->toBe(0);
});

it('accepts the clinic-hours boundary times for checkup appointments', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $first = phase3d3ConfirmedAppointment($patient);
    $second = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $first, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '08:00',
    ])->assertSessionHasNoErrors();

    // Phase 2: the last valid grid slot start is 16:30 (ends at 17:00).
    phase3d3ReschedulePost($user, $second, [
        'proposed_date' => '2026-11-21',
        'proposed_time' => '16:30',
    ])->assertSessionHasNoErrors();

    expect(AppointmentChangeRequest::count())->toBe(2);
});

it('refuses a proposed date in the past', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-10-11',
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('proposed_date');

    expect(AppointmentChangeRequest::count())->toBe(0);
});

it('refuses a proposed time earlier than now today and allows the current minute', function () {
    phase3d3Freeze('2026-10-12 10:30:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-10-12',
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('proposed_time');

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-10-12',
        'proposed_time' => '10:30',
    ])->assertSessionHasNoErrors();

    $request = AppointmentChangeRequest::sole();

    expect($request->proposed_date?->toDateString())->toBe('2026-10-12')
        ->and($request->proposed_time)->toBe('10:30');
});

/*
|--------------------------------------------------------------------------
| Delivery proposals are date-only (approved decision K5)
|--------------------------------------------------------------------------
*/

it('stores delivery proposals as date-only and ignores any submitted time', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $withTime = phase3d3DeliveryAppointment($patient);
    $withoutTime = phase3d3DeliveryAppointment($patient);

    phase3d3ReschedulePost($user, $withTime, [
        'proposed_date' => '2026-11-25',
        'proposed_time' => '15:00',
    ])->assertSessionHasNoErrors();

    phase3d3ReschedulePost($user, $withoutTime, [
        'proposed_date' => '2026-11-26',
    ])->assertSessionHasNoErrors();

    $requests = AppointmentChangeRequest::orderBy('id')->get();

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->proposed_time)->toBeNull()
        ->and($requests[1]->proposed_time)->toBeNull()
        ->and($requests[0]->proposed_date?->toDateString())->toBe('2026-11-25')
        ->and($requests[1]->proposed_date?->toDateString())->toBe('2026-11-26');
});

it('applies the date-only cutoff to delivery reschedule requests', function () {
    [$user, $patient] = phase3d3Eligible();
    $scheduledDay = phase3d3DeliveryAppointment($patient);
    $afterDay = phase3d3DeliveryAppointment($patient);

    // Through the end of the scheduled Manila day: still allowed.
    phase3d3Freeze('2026-11-10 23:30:00');
    phase3d3ReschedulePost($user, $scheduledDay, [
        'proposed_date' => '2026-11-25',
    ])->assertSessionHasNoErrors();

    // Once the scheduled day is over: refused (cutoff, 'appointment').
    phase3d3Freeze('2026-11-11 00:00:00');
    phase3d3ReschedulePost($user, $afterDay, [
        'proposed_date' => '2026-11-26',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Current-schedule cutoff (timed appointments)
|--------------------------------------------------------------------------
*/

it('refuses a reschedule request once the confirmed scheduled start has passed', function () {
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);
    $payload = ['proposed_date' => '2026-11-20', 'proposed_time' => '10:00'];

    // The start minute itself is refused, and so is every later minute.
    phase3d3Freeze('2026-11-07 14:00:00');
    phase3d3ReschedulePost($user, $appointment, $payload)
        ->assertSessionHasErrors('appointment');

    phase3d3Freeze('2026-11-07 14:01:00');
    phase3d3ReschedulePost($user, $appointment, $payload)
        ->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE')->count())->toBe(0);
});

it('allows a reschedule request one minute before the scheduled start', function () {
    phase3d3Freeze('2026-11-07 13:59:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasNoErrors();

    expect(AppointmentChangeRequest::sole()->status)
        ->toBe(AppointmentChangeRequest::STATUS_PENDING);
});

/*
|--------------------------------------------------------------------------
| Collision guard 1: identical to the current confirmed schedule (K2)
|--------------------------------------------------------------------------
*/

it('refuses a proposal identical to the current confirmed schedule', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-07',
        'proposed_time' => '14:00',
        'reason' => 'Trying to reschedule to the same slot.',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE')->count())->toBe(0);
});

it('allows a proposal on the same date with a different time', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-07',
        'proposed_time' => '15:00',
    ])->assertSessionHasNoErrors();

    expect(AppointmentChangeRequest::sole()->proposed_time)->toBe('15:00');
});

it('refuses an identical date-only delivery proposal', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3DeliveryAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-10',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Collision guard 2: exact duplicate of ANOTHER active appointment (K2)
|--------------------------------------------------------------------------
*/

it('refuses a proposal matching another confirmed appointment of the same patient and type', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);
    phase3d3ConfirmedAppointment($patient, [
        'confirmed_date' => '2026-12-01',
        'confirmed_time' => '09:00',
    ]);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-12-01',
        'proposed_time' => '09:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(0);
});

it('refuses a proposal matching another pending appointment of the same patient and type', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);
    phase3d3PendingAppointment($patient, [
        'requested_date' => '2026-12-02',
        'requested_time' => '09:00',
    ]);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-12-02',
        'proposed_time' => '09:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(0);
});

it('refuses a proposal matching a confirmed appointment slot even when the type differs (Phase 2 occupancy)', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);
    // Another CONFIRMED appointment (counseling) already occupies the
    // 2026-12-03 09:00 slot. Phase 2: one confirmed appointment per
    // slot regardless of type, so the proposal is refused.
    phase3d3ConfirmedAppointment($patient, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'confirmed_date' => '2026-12-03',
        'confirmed_time' => '09:00',
    ]);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-12-03',
        'proposed_time' => '09:00',
    ])->assertSessionHasErrors();

    expect(AppointmentChangeRequest::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| One pending change request per appointment - BOTH directions
|--------------------------------------------------------------------------
*/

it('refuses a reschedule request while another change request is pending', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3CancelRequestPost($user, $appointment, [
        'reason' => 'First I tried cancelling instead.',
    ])->assertSessionHasNoErrors();

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(1)
        ->and(AppointmentChangeRequest::sole()->type)
            ->toBe(AppointmentChangeRequest::TYPE_CANCELLATION);
});

it('refuses a cancellation request while a reschedule request is pending', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasNoErrors();

    phase3d3CancelRequestPost($user, $appointment, [
        'reason' => 'Now I want to cancel instead.',
    ])->assertSessionHasErrors('reason');

    expect(AppointmentChangeRequest::count())->toBe(1)
        ->and(AppointmentChangeRequest::sole()->type)
            ->toBe(AppointmentChangeRequest::TYPE_RESCHEDULE)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_CANCELLATION')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Eligibility boundaries: source, status, ownership
|--------------------------------------------------------------------------
*/

it('refuses a reschedule request against a walk-in appointment', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient, [
        'source' => Appointment::SOURCE_WALK_IN,
    ]);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE')->count())->toBe(0);
});

it('refuses a reschedule request against a non-confirmed appointment', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $pending = phase3d3PendingAppointment($patient);

    phase3d3ReschedulePost($user, $pending, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('appointment');

    expect($pending->fresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and(AppointmentChangeRequest::count())->toBe(0);
});

it('refuses another patient appointment with 404', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$userA] = phase3d3Eligible();
    [, $patientB] = phase3d3Eligible();
    $confirmedOfB = phase3d3ConfirmedAppointment($patientB);

    phase3d3ReschedulePost($userA, $confirmedOfB, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
        'reason' => 'Trying to reschedule a foreign row.',
    ])->assertNotFound();

    expect($confirmedOfB->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AppointmentChangeRequest::count())->toBe(0);
});

it('refuses a service-direct call that passes a different patient', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patientA] = phase3d3Eligible();
    [, $patientB] = phase3d3Eligible();
    $appointmentOfB = phase3d3ConfirmedAppointment($patientB);

    expect(fn () => app(AppointmentManagementService::class)->requestAppointmentReschedule(
        $appointmentOfB,
        $patientA, // wrong patient: ownership assertion must refuse.
        $user,
        '2026-11-20',
        '10:00',
        'Trying to bypass the controller.'
    ))->toThrow(DomainException::class);

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Authorization matrix
|--------------------------------------------------------------------------
*/

it('enforces the guest, clinic-role and portal-eligibility matrix', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$owner, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);
    $payload = ['proposed_date' => '2026-11-20', 'proposed_time' => '10:00'];

    $this->post(
        route('patient.appointments.rescheduleRequest', ['appointmentId' => $appointment->id]),
        $payload
    )->assertRedirect(route('login'));

    phase3d3ReschedulePost(phase3d3User('staff'), $appointment, $payload)->assertForbidden();
    phase3d3ReschedulePost(phase3d3User('admin'), $appointment, $payload)->assertForbidden();

    // Patient-role login with NO linked patient record.
    phase3d3ReschedulePost(phase3d3User('patient'), $appointment, $payload)->assertForbidden();

    // Archived patient record linked to a live portal account.
    $archivedUser = phase3d3User('patient');
    $archivedPatient = phase3d3Patient();
    phase3d3Link($archivedPatient, $archivedUser);
    $archivedPatient->delete();
    phase3d3ReschedulePost($archivedUser, $appointment, $payload)->assertForbidden();

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AppointmentChangeRequest::count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE')->count())->toBe(0);
});

it('ignores forged appointment fields posted alongside a valid reschedule request', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    [$otherUser, $otherPatient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
        // Server-owned fields: all ignored.
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'status' => Appointment::STATUS_CANCELLED,
        'source' => Appointment::SOURCE_WALK_IN,
        'confirmed_date' => '2027-01-01',
        'confirmed_time' => '07:00',
        'patient_id' => $otherPatient->id,
    ])->assertSessionHasNoErrors();

    $appointment->refresh();

    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->appointment_type)->toBe(Appointment::TYPE_CHECKUP)
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->source)->toBe(Appointment::SOURCE_ONLINE)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($otherPatient->appointments()->count())->toBe(0)
        ->and(AppointmentChangeRequest::sole()->type)
            ->toBe(AppointmentChangeRequest::TYPE_RESCHEDULE);
});

it('refuses a second reschedule submission while the first is pending', function () {
    phase3d3Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3Eligible();
    $appointment = phase3d3ConfirmedAppointment($patient);

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasNoErrors();

    phase3d3ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-21',
        'proposed_time' => '11:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(1)
        ->and(AppointmentChangeRequest::sole()->proposed_date?->toDateString())->toBe('2026-11-20')
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_RESCHEDULE')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Schema contract (no migrations in Phase 3D)
|--------------------------------------------------------------------------
*/

it('adds no tables or appointment columns beyond the approved schema', function () {
    expect(Schema::hasTable('appointment_change_requests'))->toBeTrue()
        ->and(Schema::hasTable('delivery_admissions'))->toBeFalse()
        ->and(Schema::hasTable('bed_occupancies'))->toBeFalse();

    $appointmentColumns = Schema::getColumnListing('appointments');

    expect($appointmentColumns)->toHaveCount(16)
        ->and($appointmentColumns)->toEqualCanonicalizing([
            'id',
            'patient_id',
            'appointment_type',
            'source',
            'requested_date',
            'requested_time',
            'confirmed_date',
            'confirmed_time',
            'status',
            'reviewed_by',
            'reviewed_at',
            'decline_reason',
            'cancellation_reason',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

    expect(Schema::getColumnListing('appointment_change_requests'))->toEqualCanonicalizing([
        'id',
        'appointment_id',
        'type',
        'proposed_date',
        'proposed_time',
        'reason',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'created_at',
        'updated_at',
    ]);
});
