<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentCancellationRequestedNotification;
use App\Notifications\AppointmentPatientCancelledNotification;
use App\Services\AppointmentManagementService;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 3D.2B - Patient Cancellation (direct pending + confirmed request)
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers: direct cancellation
| of pending ONLINE appointments by the owning patient (reason required,
| status pending->cancelled, in-tx audit, clinic notification after
| commit, no patient self-notification); approval-gated cancellation
| REQUESTS against confirmed ONLINE appointments (appointment stays
| confirmed with its schedule untouched, one pending change request per
| appointment); the approved Manila cutoff (timed = strictly before the
| start minute; date-only = through the end of the scheduled day);
| ownership/role isolation (own patientAccount link only, foreign id =
| 404, forged fields ignored); walk-in exclusions; terminal-status
| refusals; duplicate/concurrent-submission safety; notification event
| key dedupe and payload privacy; portal card UI eligibility; and staff
| cancel preservation while a request is pending. Staff approve/decline
| and rescheduling are deliberately OUT of scope (later steps).
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3d2bUser(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d2bPatient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Ana',
        'last_name' => 'Dela Cruz',
        'middle_name' => 'Reyes',
        'age' => 27,
        'email' => "phase3d2b{$sequence}@portal.test",
        'contact_number' => '09171234567',
        'address_line' => '45 Rizal Street',
        'barangay' => 'Sampaloc',
        'city_municipality' => 'Manila',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d2bLink(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3d2bEligible(): array
{
    $user = phase3d2bUser('patient');
    $patient = phase3d2bPatient();
    phase3d2bLink($patient, $user);

    return [$user, $patient];
}

function phase3d2bPendingAppointment(Patient $patient, array $overrides = []): Appointment
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

function phase3d2bConfirmedAppointment(Patient $patient, array $overrides = []): Appointment
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
    ], $overrides));
}

function phase3d2bCancelPost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)->post(
        route('patient.appointments.cancel', ['appointmentId' => $appointment->id]),
        $data
    );
}

function phase3d2bRequestPost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)->post(
        route('patient.appointments.cancellationRequest', ['appointmentId' => $appointment->id]),
        $data
    );
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3d2bFreeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

function phase3d2bCancelledNotificationRows(): array
{
    return DB::table('notifications')
        ->where('type', AppointmentPatientCancelledNotification::class)
        ->get()
        ->all();
}

function phase3d2bRequestedNotificationRows(): array
{
    return DB::table('notifications')
        ->where('type', AppointmentCancellationRequestedNotification::class)
        ->get()
        ->all();
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 1-9. Direct pending cancellation
|--------------------------------------------------------------------------
*/

it('lets the owning patient cancel their pending online appointment directly', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $admin = phase3d2bUser('admin');
    $staff = phase3d2bUser('staff');
    $appointment = phase3d2bPendingAppointment($patient);

    $response = phase3d2bCancelPost($user, $appointment, ['reason' => 'Family emergency, cannot attend.']);

    $response->assertRedirect(route('patient.appointments.index'))
        ->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->cancellation_reason)->toBe('Family emergency, cannot attend.')
        ->and($appointment->reviewed_by)->toBeNull()
        ->and($appointment->reviewed_at)->toBeNull();

    $audit = AuditLog::where('action', 'PATIENT_CANCELLED_PENDING')->sole();
    expect($audit->user_id)->toBe($user->id)
        ->and($audit->patient_id)->toBe($patient->id)
        ->and($audit->module)->toBe('APPOINTMENT');

    $rows = phase3d2bCancelledNotificationRows();
    expect($rows)->toHaveCount(2);
    $recipientIds = array_map(fn ($row) => json_decode($row->data, true)['event_key'] ?? null, $rows);
    expect($recipientIds[0])->toBe('appointment-patient-cancelled:' . $appointment->id);

    $payload = json_decode($rows[0]->data, true);
    expect($payload['type'])->toBe('APPOINTMENT_PATIENT_CANCELLED')
        ->and($payload['message'])->toContain('Checkup')
        ->and($payload['message'])->not->toContain('Dela Cruz')
        ->and($payload['destination']['route'])->toBe('appointments.index');

    $notifiedUserIds = DB::table('notifications')
        ->where('type', AppointmentPatientCancelledNotification::class)
        ->pluck('notifiable_id')
        ->sort()
        ->values()
        ->all();
    expect($notifiedUserIds)->toBe([$admin->id, $staff->id]);
    expect($notifiedUserIds)->not->toContain($user->id);
});

it('requires a cancellation reason for direct pending cancellation', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient);

    phase3d2bCancelPost($user, $appointment, ['reason' => 'ab'])
        ->assertSessionHasErrors('reason');

    phase3d2bCancelPost($user, $appointment, [])
        ->assertSessionHasErrors('reason');

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and(AuditLog::where('module', 'APPOINTMENT')->where('action', 'PATIENT_CANCELLED_PENDING')->count())->toBe(0)
        ->and(phase3d2bCancelledNotificationRows())->toBeEmpty();
});

it('refuses direct cancellation of a walk-in appointment', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient, [
        'source' => Appointment::SOURCE_WALK_IN,
    ]);

    $response = phase3d2bCancelPost($user, $appointment, ['reason' => 'Cannot attend the visit.']);

    $response->assertSessionHasErrors('reason');
    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and(AuditLog::where('action', 'PATIENT_CANCELLED_PENDING')->count())->toBe(0)
        ->and(phase3d2bCancelledNotificationRows())->toBeEmpty();
});

it('refuses direct cancellation once the scheduled start has passed', function () {
    phase3d2bFreeze('2026-11-05 10:00:00'); // the start minute itself
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient);

    $response = phase3d2bCancelPost($user, $appointment, ['reason' => 'Running late, please cancel.']);

    $response->assertSessionHasErrors('reason');
    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and(phase3d2bCancelledNotificationRows())->toBeEmpty();
});

it('allows direct cancellation one minute before the scheduled start', function () {
    phase3d2bFreeze('2026-11-05 09:59:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient);

    phase3d2bCancelPost($user, $appointment, ['reason' => 'Cannot make it in time.'])
        ->assertRedirect(route('patient.appointments.index'));

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_CANCELLED);
});

it('refuses direct cancellation once the appointment has another status', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();

    $completed = phase3d2bPendingAppointment($patient, ['status' => Appointment::STATUS_COMPLETED]);
    phase3d2bCancelPost($user, $completed, ['reason' => 'Please cancel this completed row.'])
        ->assertSessionHasErrors('reason');
    expect($completed->fresh()->status)->toBe(Appointment::STATUS_COMPLETED);

    $declined = phase3d2bPendingAppointment($patient, ['status' => Appointment::STATUS_DECLINED]);
    phase3d2bCancelPost($user, $declined, ['reason' => 'Please cancel this declined row.'])
        ->assertSessionHasErrors('reason');
    expect($declined->fresh()->status)->toBe(Appointment::STATUS_DECLINED);

    // A CONFIRMED appointment must use the request path, never direct cancel.
    $confirmed = phase3d2bConfirmedAppointment($patient);
    phase3d2bCancelPost($user, $confirmed, ['reason' => 'Please cancel this confirmed row.'])
        ->assertSessionHasErrors('reason');
    expect($confirmed->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);

    expect(AuditLog::where('action', 'PATIENT_CANCELLED_PENDING')->count())->toBe(0)
        ->and(phase3d2bCancelledNotificationRows())->toBeEmpty();
});

it('refuses a repeated direct cancellation and never duplicates audit or notifications', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    phase3d2bUser('admin');
    phase3d2bUser('staff');
    $appointment = phase3d2bPendingAppointment($patient);

    phase3d2bCancelPost($user, $appointment, ['reason' => 'First submission, please cancel.'])
        ->assertSessionHas('success');

    phase3d2bCancelPost($user, $appointment, ['reason' => 'Second submission, please cancel.'])
        ->assertSessionHasErrors('reason');

    expect(AuditLog::where('action', 'PATIENT_CANCELLED_PENDING')->count())->toBe(1)
        ->and(phase3d2bCancelledNotificationRows())->toHaveCount(2);
});

it('never notifies the patient about their own direct cancellation', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient);

    phase3d2bCancelPost($user, $appointment, ['reason' => 'Please cancel my request.'])
        ->assertSessionHas('success');

    expect($user->notifications()->count())->toBe(0);
});

it('deduplicates the clinic notification by event key on a repeated service dispatch', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    phase3d2bUser('admin');
    phase3d2bUser('staff');
    $appointment = phase3d2bPendingAppointment($patient);

    phase3d2bCancelPost($user, $appointment, ['reason' => 'Please cancel my request.']);

    $service = app(SystemNotificationService::class);
    expect($service->notifyPatientCancelledPendingAppointment($appointment->fresh()))->toBeFalse()
        ->and(phase3d2bCancelledNotificationRows())->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| 10-17. Confirmed cancellation request
|--------------------------------------------------------------------------
*/

it('creates a pending cancellation request and keeps the confirmed appointment untouched', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $admin = phase3d2bUser('admin');
    $staff = phase3d2bUser('staff');
    $appointment = phase3d2bConfirmedAppointment($patient);

    $response = phase3d2bRequestPost($user, $appointment, ['reason' => 'Plans changed, please cancel.']);

    $response->assertRedirect(route('patient.appointments.index'))
        ->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($appointment->cancellation_reason)->toBeNull();

    $request = AppointmentChangeRequest::sole();
    expect($request->appointment_id)->toBe($appointment->id)
        ->and($request->type)->toBe(AppointmentChangeRequest::TYPE_CANCELLATION)
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($request->reason)->toBe('Plans changed, please cancel.')
        ->and($request->proposed_date)->toBeNull()
        ->and($request->proposed_time)->toBeNull()
        ->and($request->reviewed_by)->toBeNull()
        ->and($request->reviewed_at)->toBeNull();

    $audit = AuditLog::where('action', 'PATIENT_REQUESTED_CANCELLATION')->sole();
    expect($audit->user_id)->toBe($user->id)
        ->and($audit->patient_id)->toBe($patient->id)
        ->and($audit->module)->toBe('APPOINTMENT');

    $rows = phase3d2bRequestedNotificationRows();
    expect($rows)->toHaveCount(2);
    $payload = json_decode($rows[0]->data, true);
    expect($payload['type'])->toBe('APPOINTMENT_CANCELLATION_REQUESTED')
        ->and($payload['event_key'])->toBe('appointment-cancel-requested:' . $request->id)
        ->and($payload['message'])->toContain('November 7, 2026')
        ->and($payload['message'])->not->toContain('Dela Cruz');

    $notifiedUserIds = DB::table('notifications')
        ->where('type', AppointmentCancellationRequestedNotification::class)
        ->pluck('notifiable_id')
        ->sort()
        ->values()
        ->all();
    expect($notifiedUserIds)->toBe([$admin->id, $staff->id]);
    expect($notifiedUserIds)->not->toContain($user->id);
});

it('requires a reason for the cancellation request', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bConfirmedAppointment($patient);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'x'])
        ->assertSessionHasErrors('reason');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and($appointment->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_CANCELLATION')->count())->toBe(0)
        ->and(phase3d2bRequestedNotificationRows())->toBeEmpty();
});

it('refuses a cancellation request against a walk-in confirmed appointment', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bConfirmedAppointment($patient, [
        'source' => Appointment::SOURCE_WALK_IN,
    ]);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'Please cancel this walk-in.'])
        ->assertSessionHasErrors('reason');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and($appointment->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('refuses a cancellation request once the confirmed schedule has passed', function () {
    phase3d2bFreeze('2026-11-07 14:30:00'); // after the 14:00 confirmed start
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bConfirmedAppointment($patient);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'Please cancel this past visit.'])
        ->assertSessionHasErrors('reason');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and($appointment->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('refuses a cancellation request against a pending appointment', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'Please convert this to a request.'])
        ->assertSessionHasErrors('reason');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and($appointment->fresh()->status)->toBe(Appointment::STATUS_PENDING);
});

it('allows only one pending change request per appointment', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bConfirmedAppointment($patient);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'First cancellation request.'])
        ->assertSessionHas('success');

    phase3d2bRequestPost($user, $appointment, ['reason' => 'Second cancellation request.'])
        ->assertSessionHasErrors('reason');

    expect(AppointmentChangeRequest::count())->toBe(1);

    // Once the pending request is resolved (simulated here; staff review
    // arrives in a later step), a NEW request is allowed again.
    AppointmentChangeRequest::query()->update(['status' => AppointmentChangeRequest::STATUS_DECLINED]);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'Third request after resolution.'])
        ->assertSessionHas('success');

    expect(AppointmentChangeRequest::count())->toBe(2)
        ->and(AppointmentChangeRequest::where('status', AppointmentChangeRequest::STATUS_PENDING)->count())->toBe(1);
});

it('shows the pending-review badge and hides further actions on the portal card', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bConfirmedAppointment($patient);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'Travel conflict, please review.']);

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Cancellation request pending')
        ->assertSee('Your cancellation reason:')
        ->assertSee('Travel conflict, please review.')
        ->assertDontSee('Request Cancellation')
        ->assertDontSee('Cancel Appointment');
});

it('preserves staff cancellation of a confirmed appointment while a patient request is pending', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    $staff = phase3d2bUser('staff');
    $appointment = phase3d2bConfirmedAppointment($patient);

    phase3d2bRequestPost($user, $appointment, ['reason' => 'Awaiting review when staff acts.']);

    // Existing staff workflow is untouched by the pending request row.
    $this->actingAs($staff)->from(route('appointments.index'))->patch(
        route('appointments.cancel', $appointment),
        ['cancellation_reason' => 'Clinic no longer available that day.']
    )->assertRedirect(route('appointments.index'))->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->cancellation_reason)->toBe('Clinic no longer available that day.');

    // Phase 3D.2C (auto-resolution, user-approved design): the staff
    // cancel closes any pending cancellation request in the SAME
    // transaction - approved, matching the appointment's new status.
    // (3D.2B originally asserted the request stayed pending; that
    // deferred state is superseded by the review step's invariant.)
    expect(AppointmentChangeRequest::where('status', AppointmentChangeRequest::STATUS_PENDING)->count())->toBe(0)
        ->and(AppointmentChangeRequest::where('status', AppointmentChangeRequest::STATUS_APPROVED)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 18-20. Ownership and authorization
|--------------------------------------------------------------------------
*/

it('refuses another patient appointment with 404 on both endpoints', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$userA] = phase3d2bEligible();
    [$userB, $patientB] = phase3d2bEligible();
    $pendingOfB = phase3d2bPendingAppointment($patientB);
    $confirmedOfB = phase3d2bConfirmedAppointment($patientB);

    phase3d2bCancelPost($userA, $pendingOfB, ['reason' => 'Trying to cancel a foreign row.'])
        ->assertNotFound();

    phase3d2bRequestPost($userA, $confirmedOfB, ['reason' => 'Trying to request on a foreign row.'])
        ->assertNotFound();

    expect($pendingOfB->fresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($confirmedOfB->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AppointmentChangeRequest::count())->toBe(0);
});

it('ignores a forged patient_id posted alongside a valid cancellation', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();
    [$otherUser, $otherPatient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient);

    phase3d2bCancelPost($user, $appointment, [
        'reason' => 'Please cancel my own appointment.',
        'patient_id' => $otherPatient->id,
        'status' => Appointment::STATUS_CONFIRMED,
        'source' => Appointment::SOURCE_WALK_IN,
    ])->assertSessionHas('success');

    $appointment->refresh();
    expect($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->source)->toBe(Appointment::SOURCE_ONLINE)
        ->and($otherPatient->appointments()->count())->toBe(0);
});

it('enforces the guest, clinic-role and portal-eligibility matrix on both endpoints', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$owner, $patient] = phase3d2bEligible();
    $pending = phase3d2bPendingAppointment($patient);
    $confirmed = phase3d2bConfirmedAppointment($patient);

    $this->post(route('patient.appointments.cancel', ['appointmentId' => $pending->id]), ['reason' => 'Guest attempt here.'])
        ->assertRedirect(route('login'));
    $this->post(route('patient.appointments.cancellationRequest', ['appointmentId' => $confirmed->id]), ['reason' => 'Guest attempt here.'])
        ->assertRedirect(route('login'));

    $clinic = phase3d2bUser('staff');
    phase3d2bCancelPost($clinic, $pending, ['reason' => 'Clinic role attempt.'])->assertForbidden();
    phase3d2bRequestPost($clinic, $confirmed, ['reason' => 'Clinic role attempt.'])->assertForbidden();

    $unlinked = phase3d2bUser('patient');
    phase3d2bCancelPost($unlinked, $pending, ['reason' => 'Unlinked account attempt.'])->assertForbidden();
    phase3d2bRequestPost($unlinked, $confirmed, ['reason' => 'Unlinked account attempt.'])->assertForbidden();

    // An ARCHIVED PATIENT RECORD (house convention) linked to a live
    // portal account is refused by the portal middleware with 403.
    $archivedUser = phase3d2bUser('patient');
    $archivedPatient = phase3d2bPatient();
    phase3d2bLink($archivedPatient, $archivedUser);
    $archivedPatient->delete();
    phase3d2bCancelPost($archivedUser, $pending, ['reason' => 'Archived patient attempt.'])->assertForbidden();
    phase3d2bRequestPost($archivedUser, $confirmed, ['reason' => 'Archived patient attempt.'])->assertForbidden();

    expect($pending->fresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($confirmed->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AppointmentChangeRequest::count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_CANCELLED_PENDING')->count())->toBe(0)
        ->and(AuditLog::where('action', 'PATIENT_REQUESTED_CANCELLATION')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 21-24. Cutoff helper + portal card UI eligibility
|--------------------------------------------------------------------------
*/

it('applies the approved Manila cutoff interpretations', function () {
    // timed appointments: allowed strictly BEFORE the start minute.
    phase3d2bFreeze('2026-11-05 09:59:00');
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-05', '10:00'))->toBeNull();   // one minute early ok
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-05', '09:58'))->not->toBeNull(); // already started
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-04', '10:00'))->not->toBeNull(); // yesterday refused

    phase3d2bFreeze('2026-11-05 10:00:00');
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-05', '10:00'))->not->toBeNull(); // equal minute refused
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-05', '10:01'))->toBeNull();     // next minute ok
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-06', '09:00'))->toBeNull();     // future day ok

    // date-only: allowed through the end of the scheduled Manila day.
    phase3d2bFreeze('2026-11-05 23:59:00');
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-05', null))->toBeNull();        // same day ok
    phase3d2bFreeze('2026-11-06 00:00:00');
    expect(AppointmentManagementService::scheduledStartPassedIssue('2026-11-05', null))->not->toBeNull();   // next day refused
});

it('renders the direct-cancel form only on eligible pending online cards', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();

    $eligible = phase3d2bPendingAppointment($patient);
    $walkIn = phase3d2bPendingAppointment($patient, ['source' => Appointment::SOURCE_WALK_IN]);
    // A pending row whose requested schedule is already past at render
    // time (yesterday relative to the frozen clock below).
    $pastStart = phase3d2bPendingAppointment($patient, [
        'requested_date' => '2026-10-13',
        'requested_time' => '10:00',
    ]);

    $response = $this->actingAs($user)->get(route('patient.appointments.index'));

    $response->assertOk();
    $content = $response->getContent();
    expect(substr_count($content, 'Cancel Appointment'))->toBe(1); // only the eligible card
    expect($content)->toContain(route('patient.appointments.cancel', ['appointmentId' => $eligible->id]))
        ->not->toContain(route('patient.appointments.cancel', ['appointmentId' => $walkIn->id]))
        ->not->toContain(route('patient.appointments.cancel', ['appointmentId' => $pastStart->id]));
});

it('renders the request-cancellation form only on eligible confirmed online cards', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();

    $eligible = phase3d2bConfirmedAppointment($patient);
    $walkIn = phase3d2bConfirmedAppointment($patient, ['source' => Appointment::SOURCE_WALK_IN]);

    $response = $this->actingAs($user)->get(route('patient.appointments.index'));

    $response->assertOk();
    $content = $response->getContent();
    expect(substr_count($content, 'Request Cancellation'))->toBe(1);
    expect($content)->toContain(route('patient.appointments.cancellationRequest', ['appointmentId' => $eligible->id]))
        ->not->toContain(route('patient.appointments.cancellationRequest', ['appointmentId' => $walkIn->id]));
});

it('renders no cancellation actions on terminal-status cards', function () {
    phase3d2bFreeze('2026-10-14 08:00:00');
    [$user, $patient] = phase3d2bEligible();

    phase3d2bPendingAppointment($patient, [
        'status' => Appointment::STATUS_COMPLETED,
        'confirmed_date' => '2026-11-08',
        'confirmed_time' => '09:00',
    ]);
    phase3d2bPendingAppointment($patient, ['status' => Appointment::STATUS_CANCELLED]);
    phase3d2bPendingAppointment($patient, ['status' => Appointment::STATUS_DECLINED]);

    $response = $this->actingAs($user)->get(route('patient.appointments.index'));

    $response->assertOk()
        ->assertDontSee('Cancel Appointment')
        ->assertDontSee('Request Cancellation')
        ->assertDontSee('Cancellation request pending');
});

it('lets a date-only delivery appointment be cancelled on its scheduled day', function () {
    phase3d2bFreeze('2026-11-05 16:30:00'); // same day, after midnight, no time set
    [$user, $patient] = phase3d2bEligible();
    $appointment = phase3d2bPendingAppointment($patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => '2026-11-05',
        'requested_time' => null,
    ]);

    phase3d2bCancelPost($user, $appointment, ['reason' => 'Contractions stopped, cancelling for now.'])
        ->assertSessionHas('success');

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_CANCELLED);
});
