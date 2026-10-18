<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentCancellationApprovedNotification;
use App\Notifications\AppointmentCancellationRequestedNotification;
use App\Notifications\AppointmentCompletedNotification;
use App\Notifications\AppointmentRescheduleApprovedNotification;
use App\Notifications\AppointmentRescheduleDeclinedNotification;
use App\Notifications\AppointmentRescheduleRequestedNotification;
use App\Services\AppointmentManagementService;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 3D.3B Step 3 - Reschedule Workflow Notifications
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the approved
| notification matrix for the patient rescheduling workflow:
|
| 1. SUBMISSION -> every ACTIVE Admin/Staff account, one row each
|    (event_key = appointment-reschedule-requested:{requestId}), the
|    required neutral message, a day-view calendar link, privacy-
|    minimized payload (no name/contact/email/reason/reviewer notes),
|    unread on creation, never the patient.
| 2. APPROVAL -> the owning linked ACTIVE portal account only
|    (event_key = appointment-reschedule-approved:{requestId}), message
|    names the NEWLY confirmed schedule, My Appointments link, no
|    reviewer note, no generic status-changed duplicate row.
| 3. DECLINE -> the owning portal account only (event_key =
|    appointment-reschedule-declined:{requestId}), message says not
|    approved + original schedule remains, NO decline explanation in
|    the payload.
|
| Safety rules: no notification for failed validation, refused or stale
| reviews, repeat submissions or auto-resolution; event-key dedupe on
| repeated dispatches; no delivery to unlinked/archived accounts or
| to other patients; links stay inside the recipient's authorized
| surface (clinic calendar for clinic rows, My Appointments for
| patient rows); cancellation notifications regression-gated in-file.
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3d3b3User(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d3b3Patient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 29,
        'email' => "phase3d3b3{$sequence}@portal.test",
        'contact_number' => '09179876543',
        'address_line' => '12 Bonifacio Street',
        'barangay' => 'San Isidro',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d3b3Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/**
 * admin + active staff + owning portal user + patient (linked).
 *
 * @return array{0: User, 1: User, 2: User, 3: Patient}
 */
function phase3d3b3World(): array
{
    $admin = phase3d3b3User('admin');
    $staff = phase3d3b3User('staff');
    $portalUser = phase3d3b3User('patient');
    $patient = phase3d3b3Patient();
    phase3d3b3Link($patient, $portalUser);

    return [$admin, $staff, $portalUser, $patient];
}

function phase3d3b3ConfirmedAppointment(Patient $patient, int $reviewerId, array $overrides = []): Appointment
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
        'reviewed_by' => $reviewerId,
        'reviewed_at' => now(),
    ], $overrides));
}

function phase3d3b3ReschedulePost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)->post(
        route('patient.appointments.rescheduleRequest', ['appointmentId' => $appointment->id]),
        $data
    );
}

function phase3d3b3CancelPost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)->post(
        route('patient.appointments.cancellationRequest', ['appointmentId' => $appointment->id]),
        $data
    );
}

function phase3d3b3Patch(User $user, string $routeName, AppointmentChangeRequest $request, array $data)
{
    return test()->actingAs($user)->patch(route($routeName, ['changeRequest' => $request->id]), $data);
}

/** Pending reschedule request through the real patient service path. */
function phase3d3b3ServiceRequest(
    Appointment $appointment,
    Patient $patient,
    ?User $actor = null,
    string $date = '2026-11-20',
    ?string $time = '10:00',
    ?string $reason = null,
): AppointmentChangeRequest {
    $actor ??= User::query()->find($patient->user_id);

    return app(AppointmentManagementService::class)
        ->requestAppointmentReschedule($appointment, $patient, $actor, $date, $time, $reason);
}

/** Direct fixture for request rows the patient path cannot create. */
function phase3d3b3FixtureRequest(Appointment $appointment, array $overrides = []): AppointmentChangeRequest
{
    return AppointmentChangeRequest::forceCreate(array_merge([
        'appointment_id' => $appointment->id,
        'type' => AppointmentChangeRequest::TYPE_RESCHEDULE,
        'proposed_date' => '2026-11-21',
        'proposed_time' => '11:00',
        'status' => AppointmentChangeRequest::STATUS_PENDING,
    ], $overrides));
}

/** @return array<int, object> */
function phase3d3b3Rows(string $notificationClass): array
{
    return DB::table('notifications')
        ->where('type', $notificationClass)
        ->get()
        ->all();
}

/** @return array<string, mixed> */
function phase3d3b3Payload(object $row): array
{
    return json_decode((string) $row->data, true);
}

function phase3d3b3TotalRows(): int
{
    return DB::table('notifications')->count();
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3d3b3Freeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Submission -> clinic
|--------------------------------------------------------------------------
*/

it('notifies every active admin and staff once per submission with the required message, event key and calendar link', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [$admin, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);

    phase3d3b3ReschedulePost($portalUser, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasNoErrors();

    $changeRequest = AppointmentChangeRequest::sole();
    $rows = phase3d3b3Rows(AppointmentRescheduleRequestedNotification::class);

    expect(phase3d3b3TotalRows())->toBe(2)
        ->and($rows)->toHaveCount(2);

    $notifiedIds = collect($rows)->pluck('notifiable_id')
        ->map(fn ($id) => (int) $id)->sort()->values()->all();

    expect($notifiedIds)->toBe([$admin->id, $staff->id]);

    foreach ($rows as $row) {
        expect(phase3d3b3Payload($row))
            ->toMatchArray([
                'type' => 'APPOINTMENT_RESCHEDULE_REQUESTED',
                'title' => 'Reschedule Request Submitted',
                'message' => 'A patient has requested to reschedule an appointment.',
                'action_label' => 'View day',
                'event_key' => 'appointment-reschedule-requested:' . $changeRequest->id,
            ])
            ->and(phase3d3b3Payload($row)['destination']['route'])->toBe('appointments.index')
            ->and(phase3d3b3Payload($row)['destination']['parameters'])->toBe([
                'view' => 'day',
                'date' => '2026-11-07',
                'status' => 'all',
            ])
            ->and($row->read_at)->toBeNull();
    }

    // The submitting patient is never notified about their own action.
    expect($portalUser->notifications()->count())->toBe(0);
});

it('never notifies archived clinic accounts or unrelated patient accounts on submission', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [$admin, $staff, $portalUser, $patient] = phase3d3b3World();
    $archivedStaff = phase3d3b3User('staff');
    $archivedStaff->delete();
    // Role=patient login: must never appear among clinic recipients.
    $otherPortalUser = phase3d3b3User('patient');
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);

    phase3d3b3ReschedulePost($portalUser, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasNoErrors();

    $rows = phase3d3b3Rows(AppointmentRescheduleRequestedNotification::class);
    $notifiedIds = collect($rows)->pluck('notifiable_id')->map(fn ($id) => (int) $id)->all();

    expect($notifiedIds)->toHaveCount(2)
        ->and($notifiedIds)->toContain($admin->id, $staff->id)
        ->and($notifiedIds)->not->toContain($archivedStaff->id)
        ->and($notifiedIds)->not->toContain($otherPortalUser->id)
        ->and($otherPortalUser->notifications()->count())->toBe(0)
        ->and(phase3d3b3TotalRows())->toBe(2);
});

it('keeps the patient name, contact, email and reason out of the submission payload', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);

    phase3d3b3ReschedulePost($portalUser, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
        'reason' => 'Shift swap needs a morning slot instead.',
    ])->assertSessionHasNoErrors();

    $rows = phase3d3b3Rows(AppointmentRescheduleRequestedNotification::class);

    expect($rows)->toHaveCount(2);

    foreach ($rows as $row) {
        $raw = (string) $row->data;

        expect($raw)->not->toContain('Maria');
        expect($raw)->not->toContain('Santos');
        expect($raw)->not->toContain($patient->email);
        expect($raw)->not->toContain('09179876543');
        expect($raw)->not->toContain('Shift swap needs a morning slot instead.');
    }
});

it('dispatches no notification when submission validation fails or the service refuses', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);

    // Validation failure: proposed date in the past.
    phase3d3b3ReschedulePost($portalUser, $appointment, [
        'proposed_date' => '2026-10-11',
        'proposed_time' => '10:00',
    ])->assertSessionHasErrors('proposed_date');

    // Service refusal: identical to the current confirmed schedule.
    phase3d3b3ReschedulePost($portalUser, $appointment, [
        'proposed_date' => '2026-11-07',
        'proposed_time' => '14:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(0)
        ->and(phase3d3b3TotalRows())->toBe(0);
});

it('adds no second notification when a repeat submission is refused while the first is pending', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);

    phase3d3b3ReschedulePost($portalUser, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHasNoErrors();

    phase3d3b3ReschedulePost($portalUser, $appointment, [
        'proposed_date' => '2026-11-21',
        'proposed_time' => '11:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(1)
        ->and(phase3d3b3Rows(AppointmentRescheduleRequestedNotification::class))->toHaveCount(2)
        ->and(phase3d3b3TotalRows())->toBe(2);
});

it('deduplicates the submission on a repeated service dispatch by event key', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, , $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);
    $request = phase3d3b3ServiceRequest($appointment, $patient);

    $notifications = app(SystemNotificationService::class);
    $first = $notifications->notifyAppointmentRescheduleRequested($request);
    $second = $notifications->notifyAppointmentRescheduleRequested($request);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and(phase3d3b3Rows(AppointmentRescheduleRequestedNotification::class))->toHaveCount(2)
        ->and(phase3d3b3TotalRows())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Approval -> owning portal user
|--------------------------------------------------------------------------
*/

it('approval notifies only the owning portal user with the newly confirmed schedule and no generic status row', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [$admin, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);
    $request = phase3d3b3ServiceRequest($appointment, $patient);

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $request, [
        'review_note' => 'Capacity confirmed with the patient.',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $rows = phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class);

    // The SINGLE patient notification: exactly one row, no generic
    // status-changed row alongside it, no clinic recipients.
    expect(phase3d3b3TotalRows())->toBe(1)
        ->and($rows)->toHaveCount(1)
        ->and((int) $rows[0]->notifiable_id)->toBe($portalUser->id)
        ->and($rows[0]->read_at)->toBeNull()
        ->and(phase3d3b3Payload($rows[0]))
            ->toMatchArray([
                'type' => 'APPOINTMENT_RESCHEDULE_APPROVED',
                'title' => 'Reschedule Request Approved',
                'action_label' => 'View My Appointments',
                'event_key' => 'appointment-reschedule-approved:' . $request->id,
            ])
        ->and(phase3d3b3Payload($rows[0])['message'])
            ->toContain('November 20, 2026 at 10:00')
        ->and(phase3d3b3Payload($rows[0])['destination']['route'])
            ->toBe('patient.appointments.index');

    $raw = (string) $rows[0]->data;

    expect($raw)->not->toContain('Capacity confirmed with the patient.');
    expect($raw)->not->toContain('Maria');
    expect($raw)->not->toContain($patient->email);
    expect($admin->notifications()->count())->toBe(0);
    expect($staff->notifications()->count())->toBe(0);
});

it('approval of a delivery reschedule announces the date-only schedule without a time', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
        'confirmed_date' => '2026-11-10',
        'confirmed_time' => null,
    ]);
    $request = phase3d3b3ServiceRequest($appointment, $patient, null, '2026-11-25', null);

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasNoErrors();

    $rows = phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class);

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]->notifiable_id)->toBe($portalUser->id)
        ->and(phase3d3b3Payload($rows[0])['message'])
            ->toContain('November 25, 2026');

    expect(phase3d3b3Payload($rows[0])['message'])->not->toContain(' at ');
});

it('never notifies unlinked or archived portal accounts on approval while the review still succeeds', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);

    // Unlinked patient record: fixture request, no portal account exists.
    $unlinked = phase3d3b3Patient();
    $unlinkedAppointment = phase3d3b3ConfirmedAppointment($unlinked, $staff->id);
    $unlinkedRequest = phase3d3b3FixtureRequest($unlinkedAppointment);

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $unlinkedRequest, [])
        ->assertSessionHasNoErrors();

    // Linked but ARCHIVED portal account at review time.
    $request = phase3d3b3ServiceRequest($appointment, $patient);
    $portalUser->delete();

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasNoErrors();

    expect($unlinkedRequest->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and(phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class))->toBeEmpty()
        ->and(phase3d3b3TotalRows())->toBe(0);
});

it('dispatches no reschedule notification when the approval is refused by a review-time duplicate', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, , $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);
    $request = phase3d3b3ServiceRequest($appointment, $patient);

    // Exact duplicate appears between submission and review.
    phase3d3b3ConfirmedAppointment($patient, $staff->id, [
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => '10:00',
    ]);

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and(phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class))->toBeEmpty()
        ->and(phase3d3b3Rows(AppointmentRescheduleDeclinedNotification::class))->toBeEmpty()
        ->and(phase3d3b3TotalRows())->toBe(0);
});

it('refuses a stale second approval without duplicating the outcome notification', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, , $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);
    $request = phase3d3b3ServiceRequest($appointment, $patient);

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasNoErrors();
    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect(phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class))->toHaveCount(1)
        ->and(phase3d3b3TotalRows())->toBe(1);
});

it('deduplicates the approval notification on a repeated service dispatch by event key', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, , $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);
    $request = phase3d3b3ServiceRequest($appointment, $patient);

    app(AppointmentManagementService::class)
        ->approveRescheduleRequest($request, $staff, 'Reviewed and approved.');

    $notifications = app(SystemNotificationService::class);
    $first = $notifications->notifyAppointmentRescheduleApproved($request->fresh());
    $second = $notifications->notifyAppointmentRescheduleApproved($request->fresh());

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and(phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class))->toHaveCount(1)
        ->and(phase3d3b3TotalRows())->toBe(1);
});

it('does not deliver an outcome notification to other patients or clinic accounts', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [$admin, $staff, $portalUserA, $patientA] = phase3d3b3World();
    $worldB = phase3d3b3World();
    $portalUserB = $worldB[2];
    $patientB = $worldB[3];

    $appointmentA = phase3d3b3ConfirmedAppointment($patientA, $staff->id);
    $requestA = phase3d3b3ServiceRequest($appointmentA, $patientA);

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $requestA, [])
        ->assertSessionHasNoErrors();

    $rows = phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class);

    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]->notifiable_id)->toBe($portalUserA->id)
        ->and($portalUserB->notifications()->count())->toBe(0)
        ->and($patientB->appointments()->count())->toBe(0)
        ->and($admin->notifications()->count())->toBe(0)
        ->and($staff->notifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Decline -> owning portal user
|--------------------------------------------------------------------------
*/

it('decline notifies only the owning portal user and keeps the explanation out of the payload', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [$admin, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);
    $request = phase3d3b3ServiceRequest($appointment, $patient);

    phase3d3b3Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'No available slot that day.',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $rows = phase3d3b3Rows(AppointmentRescheduleDeclinedNotification::class);

    expect(phase3d3b3TotalRows())->toBe(1)
        ->and($rows)->toHaveCount(1)
        ->and((int) $rows[0]->notifiable_id)->toBe($portalUser->id)
        ->and($rows[0]->read_at)->toBeNull()
        ->and(phase3d3b3Payload($rows[0]))
            ->toMatchArray([
                'type' => 'APPOINTMENT_RESCHEDULE_DECLINED',
                'title' => 'Reschedule Request Declined',
                'action_label' => 'View My Appointments',
                'event_key' => 'appointment-reschedule-declined:' . $request->id,
            ])
        ->and(phase3d3b3Payload($rows[0])['message'])
            ->toContain('was not approved')
            ->toContain('November 7, 2026')
            ->toContain('remains confirmed')
        ->and(phase3d3b3Payload($rows[0])['destination']['route'])
            ->toBe('patient.appointments.index');

    $raw = (string) $rows[0]->data;

    // The decline explanation never travels through the notification.
    expect($raw)->not->toContain('No available slot that day.');
    expect($admin->notifications()->count())->toBe(0);
    expect($staff->notifications()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Auto-resolution
|--------------------------------------------------------------------------
*/

it('auto-resolution by staff completion sends no reschedule outcome notification', function () {
    // QA FIX 2: freeze one hour before the fixture's 14:00 start - the
    // reschedule request is still submittable and the completion that
    // follows is due today (2026-11-07).
    phase3d3b3Freeze('2026-11-07 13:00:00');
    [, $staff, , $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);
    $request = phase3d3b3ServiceRequest($appointment, $patient);

    $this->actingAs($staff)->patch(route('appointments.complete', $appointment), [])
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        // No reschedule outcome notification: the appointment's own
        // COMPLETED status notification already covers the outcome.
        ->and(phase3d3b3Rows(AppointmentRescheduleDeclinedNotification::class))->toBeEmpty()
        ->and(phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class))->toBeEmpty()
        ->and(phase3d3b3Rows(AppointmentCompletedNotification::class))->toHaveCount(1)
        ->and(phase3d3b3Rows(AppointmentRescheduleRequestedNotification::class))->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| Cancellation regression (unchanged 3D.2B/3D.2C behaviour)
|--------------------------------------------------------------------------
*/

it('keeps cancellation submissions and reviews on their own notification types with no reschedule rows', function () {
    phase3d3b3Freeze('2026-10-12 09:00:00');
    [, $staff, $portalUser, $patient] = phase3d3b3World();
    $appointment = phase3d3b3ConfirmedAppointment($patient, $staff->id);

    phase3d3b3CancelPost($portalUser, $appointment, [
        'reason' => 'Family emergency, cannot attend.',
    ])->assertSessionHasNoErrors();

    $changeRequest = AppointmentChangeRequest::sole();

    expect(phase3d3b3Rows(AppointmentCancellationRequestedNotification::class))->toHaveCount(2)
        ->and(phase3d3b3Rows(AppointmentRescheduleRequestedNotification::class))->toBeEmpty()
        ->and(phase3d3b3TotalRows())->toBe(2);

    phase3d3b3Patch($staff, 'appointment-change-requests.approve', $changeRequest, [
        'review_note' => 'Confirmed with the patient.',
    ])->assertSessionHasNoErrors();

    expect(phase3d3b3Rows(AppointmentCancellationApprovedNotification::class))->toHaveCount(1)
        ->and((int) phase3d3b3Rows(AppointmentCancellationApprovedNotification::class)[0]->notifiable_id)
            ->toBe($portalUser->id)
        ->and(phase3d3b3Rows(AppointmentRescheduleApprovedNotification::class))->toBeEmpty()
        ->and(phase3d3b3Rows(AppointmentRescheduleDeclinedNotification::class))->toBeEmpty()
        ->and(phase3d3b3TotalRows())->toBe(3);
});
