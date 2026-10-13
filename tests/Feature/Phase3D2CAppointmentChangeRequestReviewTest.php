<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentCancellationApprovedNotification;
use App\Notifications\AppointmentCancellationDeclinedNotification;
use App\Notifications\AppointmentCompletedNotification;
use App\Services\AppointmentManagementService;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Phase 3D.2C - Admin/Staff Review of Patient Cancellation Requests
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers: approve (appointment
| cancelled with the PATIENT's reason, request stamped, single patient
| notification, no double status notification); decline (appointment stays
| confirmed, REQUIRED explanation stored, single patient notification);
| authorization boundaries (patient 403, guest redirect, invalid id 404);
| stale/repeat reviews (twice refused, no duplicate state); cutoff refusal
| on approve after the scheduled start; walk-in source refusal; in-tx
| auto-resolution on staff cancel/complete (cancellation+cancel -> auto-
| approved, everything else -> auto-declined, NO extra patient notifica-
| tion beyond the existing status notification); calendar UI payload
| (details request block + chip/day badges, filters preserved); portal
| card outcome display (approved banner, declined explanation); event_key
| dedupe; and the schema contract (no new tables/columns beyond Phase
| 3D.2A). Reschedule review itself lives in Phase 3D.3B (Step 2) -
| since then, reschedule-type requests are dispatched to their own
| approve/decline path, so this file's type-refusal test targets an
| UNRECOGNIZED type only.
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3d2cUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d2cPatient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 29,
        'email' => "phase3d2c{$sequence}@portal.test",
        'contact_number' => '09179876543',
        'address_line' => '12 Bonifacio Street',
        'barangay' => 'San Isidro',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d2cLink(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3d2cEligible(): array
{
    $user = phase3d2cUser('patient');
    $patient = phase3d2cPatient();
    phase3d2cLink($patient, $user);

    return [$user, $patient];
}

function phase3d2cConfirmedAppointment(Patient $patient, array $overrides = []): Appointment
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
        'reviewed_by' => phase3d2cUser('staff')->id,
        'reviewed_at' => now(),
    ], $overrides));
}

/** Pending cancellation request created through the real service path. */
function phase3d2cPendingRequest(Appointment $appointment, Patient $patient, string $reason = 'Family emergency, please cancel.'): AppointmentChangeRequest
{
    $portalUser = User::query()->find($patient->user_id) ?? phase3d2cUser('patient');

    return app(AppointmentManagementService::class)
        ->requestAppointmentCancellation($appointment, $patient, $portalUser, $reason);
}

/** Direct fixture for request rows the patient path cannot create. */
function phase3d2cFixtureRequest(Appointment $appointment, array $overrides = []): AppointmentChangeRequest
{
    return AppointmentChangeRequest::forceCreate(array_merge([
        'appointment_id' => $appointment->id,
        'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
        'reason' => 'Fixture reason for review testing.',
        'status' => AppointmentChangeRequest::STATUS_PENDING,
    ], $overrides));
}

function phase3d2cPatch(User $user, string $routeName, AppointmentChangeRequest $request, array $data)
{
    return test()->actingAs($user)->patch(route($routeName, ['changeRequest' => $request->id]), $data);
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3d2cFreeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

function phase3d2cApprovedNotificationRows(): array
{
    return DB::table('notifications')
        ->where('type', AppointmentCancellationApprovedNotification::class)
        ->get()
        ->all();
}

function phase3d2cDeclinedNotificationRows(): array
{
    return DB::table('notifications')
        ->where('type', AppointmentCancellationDeclinedNotification::class)
        ->get()
        ->all();
}

function phase3d2cPatientStatusNotificationRows(): array
{
    return DB::table('notifications')
        ->where('type', AppointmentCancelledNotification::class)
        ->get()
        ->all();
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Approve path
|--------------------------------------------------------------------------
*/

it('approve cancels the appointment with the patient reason and stamps the request', function () {
    [$portalUser, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient, 'Travelling abroad, cannot attend.');
    $staff = phase3d2cUser('staff');
    $originalReviewer = $appointment->reviewed_by;

    $response = phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, [
        'review_note' => 'Confirmed with patient over the phone.',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $appointment->refresh();
    $request->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->cancellation_reason)->toBe('Travelling abroad, cannot attend.')
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($appointment->reviewed_by)->toBe($originalReviewer)
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($request->reviewed_by)->toBe($staff->id)
        ->and($request->reviewed_at)->not->toBeNull()
        ->and($request->review_note)->toBe('Confirmed with patient over the phone.');

    expect(AuditLog::where('action', 'CANCELLATION_REQUEST_APPROVED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'CANCELLATION_REQUEST_APPROVED')->first()->patient_id)->toBe($patient->id);
});

it('approve accepts an empty optional staff note', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, ['review_note' => ''])
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($request->review_note)->toBeNull()
        ->and($appointment->refresh()->status)->toBe(Appointment::STATUS_CANCELLED);
});

it('approve sends exactly one patient notification and no double status notification', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, []);

    $approved = phase3d2cApprovedNotificationRows();

    expect($approved)->toHaveCount(1)
        ->and(json_decode($approved[0]->data, true)['type'])->toBe('APPOINTMENT_CANCELLATION_APPROVED')
        ->and(json_decode($approved[0]->data, true)['event_key'])->toBe('appointment-cancellation-approved:' . $request->id)
        ->and($approved[0]->notifiable_id)->toBe($patient->user_id)
        ->and(phase3d2cDeclinedNotificationRows())->toBeEmpty()
        ->and(phase3d2cPatientStatusNotificationRows())->toBeEmpty();
});

it('approve twice is refused with no duplicate state, audit or notification', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, []);
    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and(AuditLog::where('action', 'CANCELLATION_REQUEST_APPROVED')->count())->toBe(1)
        ->and(phase3d2cApprovedNotificationRows())->toHaveCount(1);
});

it('approve after the scheduled start passed is refused and changes nothing', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cFreeze('2026-11-07 14:00'); // start minute itself is refused.

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and(phase3d2cApprovedNotificationRows())->toBeEmpty();
});

it('approve refuses a request attached to a walk-in appointment', function () {
    [, $patient] = phase3d2cEligible();
    $walkIn = phase3d2cConfirmedAppointment($patient, [
        'source' => Appointment::SOURCE_WALK_IN,
    ]);
    $request = phase3d2cFixtureRequest($walkIn);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect($walkIn->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING);
});

it('approve refuses a request type the review workflow does not recognize', function () {
    // Deliberate Phase 3D.3B Step 2 update: this test previously used a
    // RESCHEDULE-type fixture, when only cancellation requests were
    // reviewable. Reschedule requests are now dispatched to their own
    // approve/decline path (Phase3D3B2StaffRescheduleReviewTest), so
    // the surviving safety property is: an UNRECOGNIZED type is refused
    // through the same session-error path with zero writes.
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cFixtureRequest($appointment, [
        'type' => 'transport',
    ]);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING);
});

/*
|--------------------------------------------------------------------------
| Decline path
|--------------------------------------------------------------------------
*/

it('decline keeps the appointment confirmed and stores the required explanation', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'The schedule no longer works for clinic capacity.',
    ])->assertSessionHasNoErrors();

    $appointment->refresh();
    $request->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($appointment->cancellation_reason)->toBeNull()
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and($request->reviewed_by)->toBe($staff->id)
        ->and($request->review_note)->toBe('The schedule no longer works for clinic capacity.');

    expect(AuditLog::where('action', 'CANCELLATION_REQUEST_DECLINED')->count())->toBe(1);
});

it('decline requires an explanation (422)', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, [])
        ->assertSessionHasErrors('review_note');

    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, ['review_note' => 'ab'])
        ->assertSessionHasErrors('review_note');

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(phase3d2cDeclinedNotificationRows())->toBeEmpty();
});

it('decline sends exactly one patient declined notification and no approved one', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Please keep the appointment.',
    ]);

    $declined = phase3d2cDeclinedNotificationRows();

    expect($declined)->toHaveCount(1)
        ->and(json_decode($declined[0]->data, true)['type'])->toBe('APPOINTMENT_CANCELLATION_DECLINED')
        ->and(json_decode($declined[0]->data, true)['message'])->not->toContain('Please keep the appointment.')
        ->and($declined[0]->notifiable_id)->toBe($patient->user_id)
        ->and(phase3d2cApprovedNotificationRows())->toBeEmpty()
        ->and(phase3d2cPatientStatusNotificationRows())->toBeEmpty();
});

it('decline after a stale state (request already approved) is refused', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, []);
    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Trying to decline a finished review.',
    ])->assertSessionHasErrors('change_request');

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and(phase3d2cDeclinedNotificationRows())->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Authorization boundaries
|--------------------------------------------------------------------------
*/

it('denies patient accounts, redirects guests and 404s invalid ids', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);

    // Guest check FIRST: actingAs persists for every later call in the
    // same test instance, so the unauthenticated assertion must run
    // before any actingAs() is applied.
    $this->patch(route('appointment-change-requests.approve', ['changeRequest' => $request->id]), [])
        ->assertRedirect(route('login'));

    $patientUser = User::query()->find($patient->user_id);

    $this->actingAs($patientUser)->patch(
        route('appointment-change-requests.approve', ['changeRequest' => $request->id]),
        []
    )->assertForbidden();

    $this->actingAs($patientUser)->patch(
        route('appointment-change-requests.decline', ['changeRequest' => $request->id]),
        ['review_note' => 'Patient attempting self-review.']
    )->assertForbidden();

    $staff = phase3d2cUser('staff');
    $this->actingAs($staff)->patch('/appointment-change-requests/999999/approve', [])
        ->assertNotFound();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

/*
|--------------------------------------------------------------------------
| Stale reviews + auto-resolution
|--------------------------------------------------------------------------
*/

it('refuses a stale approve after staff cancelled the appointment directly (auto-approved in-tx)', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    $this->actingAs($staff)->patch(route('appointments.cancel', $appointment), [
        'cancellation_reason' => 'Clinic closed that day.',
    ])->assertSessionHasNoErrors();

    // Auto-resolution ran in the same transaction as the staff cancel.
    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($request->reviewed_by)->toBe($staff->id)
        ->and($request->review_note)->toContain('Approved automatically');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    // Auto-resolution sent NO extra request-level notification: only the
    // appointment's own status notification.
    expect(phase3d2cApprovedNotificationRows())->toBeEmpty()
        ->and(phase3d2cPatientStatusNotificationRows())->toHaveCount(1);

    expect(AuditLog::where('action', 'CANCELLATION_REQUEST_AUTO_APPROVED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'APPOINTMENT_CANCELLED')->count())->toBe(1);
});

it('refuses a stale approve after staff completed the appointment (auto-declined in-tx)', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    $this->actingAs($staff)->patch(route('appointments.complete', $appointment), [])
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and($request->review_note)->toContain('Declined automatically');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED)
        ->and(AuditLog::where('action', 'CHANGE_REQUEST_AUTO_DECLINED')->count())->toBe(1)
        ->and(phase3d2cApprovedNotificationRows())->toBeEmpty()
        // The ONLY patient notification is the appointment's own
        // COMPLETED status notification (not the cancelled-type helper).
        ->and(DB::table('notifications')->where('type', AppointmentCompletedNotification::class)->count())->toBe(1)
        ->and(phase3d2cDeclinedNotificationRows())->toBeEmpty();
});

it('auto-declines a pending reschedule-type request when staff cancels the appointment', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cFixtureRequest($appointment, [
        'type' => AppointmentChangeRequest::TYPE_RESCHEDULE,
        'proposed_date' => '2026-11-09',
        'proposed_time' => '09:00',
    ]);
    $staff = phase3d2cUser('staff');

    $this->actingAs($staff)->patch(route('appointments.cancel', $appointment), [
        'cancellation_reason' => 'Clinic schedule changed.',
    ])->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and($request->reviewed_by)->toBe($staff->id)
        ->and(AuditLog::where('action', 'CHANGE_REQUEST_AUTO_DECLINED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'CANCELLATION_REQUEST_AUTO_APPROVED')->count())->toBe(0);
});

it('staff cancel with no pending request writes no auto-resolution audit rows', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $staff = phase3d2cUser('staff');

    $this->actingAs($staff)->patch(route('appointments.cancel', $appointment), [
        'cancellation_reason' => 'No request attached here.',
    ])->assertSessionHasNoErrors();

    expect(AuditLog::where('action', 'APPOINTMENT_CANCELLED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'CANCELLATION_REQUEST_AUTO_APPROVED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'CHANGE_REQUEST_AUTO_DECLINED')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Calendar UI
|--------------------------------------------------------------------------
*/

it('embeds the pending request block with review URLs in the day details payload', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient, 'Work conflict, please cancel.');
    $staff = phase3d2cUser('staff');

    $response = $this->actingAs($staff)->get(route('appointments.index', [
        'view' => 'day',
        'date' => '2026-11-07',
        'status' => 'all',
    ]));

    $response->assertOk();
    $content = $response->getContent();

    expect($content)
        ->toContain('"cancellationRequest"')
        ->toContain('Work conflict, please cancel.')
        ->toContain(route('appointment-change-requests.approve', ['changeRequest' => $request->id]))
        ->toContain(route('appointment-change-requests.decline', ['changeRequest' => $request->id]));
});

it('shows the pending request block under the confirmed status filter', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient, 'Filtered view reason.');
    $staff = phase3d2cUser('staff');

    $content = $this->actingAs($staff)->get(route('appointments.index', [
        'view' => 'day',
        'date' => '2026-11-07',
        'status' => 'confirmed',
    ]))->getContent();

    expect($content)
        ->toContain('"cancellationRequest"')
        ->toContain('Filtered view reason.')
        ->toContain(route('appointment-change-requests.approve', ['changeRequest' => $request->id]));
});

it('flags the appointment on month chips and the day row badge', function () {
    [, $patient] = phase3d2cEligible();
    phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest(phase3d2cConfirmedAppointment($patient), $patient);
    $staff = phase3d2cUser('staff');

    $month = $this->actingAs($staff)->get(route('appointments.index', ['date' => '2026-11-07']));
    $month->assertOk();
    expect($month->getContent())
        ->toContain('Cancellation request pending review')
        ->toContain('hasPendingCancellationRequest');

    $day = $this->actingAs($staff)->get(route('appointments.index', [
        'view' => 'day',
        'date' => '2026-11-07',
        'status' => 'all',
    ]));
    $day->assertOk();
    expect($day->getContent())->toContain('Cancellation Request');
});

it('stops flagging the appointment after the request is reviewed', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Keeping the confirmed schedule.',
    ]);

    $content = $this->actingAs($staff)->get(route('appointments.index', [
        'view' => 'day',
        'date' => '2026-11-07',
        'status' => 'all',
    ]))->getContent();

    expect($content)
        ->toContain('"cancellationRequest":null')
        ->not->toContain('Cancellation request pending review');
});

/*
|--------------------------------------------------------------------------
| Portal outcome display
|--------------------------------------------------------------------------
*/

it('shows the declined explanation on the patient portal card', function () {
    [$portalUser, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Clinic prefers to keep this slot confirmed.',
    ]);

    $content = $this->actingAs($portalUser)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('Your cancellation request was declined.')
        ->toContain('Clinic prefers to keep this slot confirmed.')
        ->not->toContain('Cancellation request pending — awaiting clinic review.');
});

it('shows the approved outcome on the cancelled patient portal card', function () {
    [$portalUser, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient, 'Cannot attend due to travel.');
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, []);

    $content = $this->actingAs($portalUser)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('Your cancellation request was approved.')
        ->toContain('Cannot attend due to travel.')
        ->not->toContain('Cancellation request pending — awaiting clinic review.');
});

/*
|--------------------------------------------------------------------------
| Notification dedupe + schema contract
|--------------------------------------------------------------------------
*/

it('dedupes the approved notification by event key on repeated dispatch', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.approve', $request, []);

    $service = app(SystemNotificationService::class);

    expect($service->notifyAppointmentCancellationApproved($request->fresh()))->toBeFalse()
        ->and(phase3d2cApprovedNotificationRows())->toHaveCount(1);
});

it('dedupes the declined notification by event key on repeated dispatch', function () {
    [, $patient] = phase3d2cEligible();
    $appointment = phase3d2cConfirmedAppointment($patient);
    $request = phase3d2cPendingRequest($appointment, $patient);
    $staff = phase3d2cUser('staff');

    phase3d2cPatch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Keeping this appointment confirmed.',
    ]);

    $service = app(SystemNotificationService::class);

    expect($service->notifyAppointmentCancellationDeclined($request->fresh()))->toBeFalse()
        ->and(phase3d2cDeclinedNotificationRows())->toHaveCount(1);
});

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
