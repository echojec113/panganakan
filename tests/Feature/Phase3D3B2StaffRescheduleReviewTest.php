<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentCancellationApprovedNotification;
use App\Notifications\AppointmentRescheduleApprovedNotification;
use App\Notifications\AppointmentRescheduleDeclinedNotification;
use App\Services\AppointmentManagementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 3D.3B Step 2 - Admin/Staff Review of Patient Reschedule Requests
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers: approval applying
| the patient's EXACT proposed schedule (K4 - staff input ignored,
| delivery date-only with confirmed_time NULL per K5, requested_* and
| the appointment's original reviewer preserved); reviewer provenance
| on the REQUEST row; RESCHEDULE_REQUEST_APPROVED/DECLINED audits
| carrying old and new schedules; both K2/K5 revalidations at review
| time (exact duplicate active appointment appearing AFTER submission,
| expired proposal, current appointment cutoff, identical proposal);
| decline keeping the original schedule with a required explanation and
| remaining available for EXPIRED proposals; stale reviews (approve
| twice, decline-after-approve, approve-after-decline, staff cancel/
| complete auto-resolution first); refusal boundaries (cancelled/
| completed appointment, walk-in source, unknown request type rejected
| safely - never a 500); the cancellation-type dispatch regression
| through the SAME endpoints; and the Step 3 outcome notifications
| (one patient row per successful review, none for refused or stale
| reviews, no duplicate generic status-changed row). Existing
| cancellation review behaviour is regression-gated by the unchanged
| Phase3D2C suite.
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3d3b2User(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d3b2Patient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 29,
        'email' => "phase3d3b2{$sequence}@portal.test",
        'contact_number' => '09179876543',
        'address_line' => '12 Bonifacio Street',
        'barangay' => 'San Isidro',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d3b2Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3d3b2Eligible(): array
{
    $user = phase3d3b2User('patient');
    $patient = phase3d3b2Patient();
    phase3d3b2Link($patient, $user);

    return [$user, $patient];
}

function phase3d3b2ConfirmedAppointment(Patient $patient, array $overrides = []): Appointment
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
        'reviewed_by' => phase3d3b2User('staff')->id,
        'reviewed_at' => now(),
    ], $overrides));
}

function phase3d3b2DeliveryAppointment(Patient $patient, array $overrides = []): Appointment
{
    return phase3d3b2ConfirmedAppointment($patient, array_merge([
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
        'confirmed_date' => '2026-11-10',
        'confirmed_time' => null,
    ], $overrides));
}

/** Pending reschedule request created through the real patient service path. */
function phase3d3b2RescheduleRequest(
    Appointment $appointment,
    Patient $patient,
    string $date = '2026-11-20',
    ?string $time = '10:00',
): AppointmentChangeRequest {
    $portalUser = User::query()->find($patient->user_id) ?? phase3d3b2User('patient');

    return app(AppointmentManagementService::class)
        ->requestAppointmentReschedule($appointment, $patient, $portalUser, $date, $time, null);
}

/** Direct fixture for request rows the patient path cannot create. */
function phase3d3b2FixtureRequest(Appointment $appointment, array $overrides = []): AppointmentChangeRequest
{
    return AppointmentChangeRequest::forceCreate(array_merge([
        'appointment_id' => $appointment->id,
        'type' => AppointmentChangeRequest::TYPE_RESCHEDULE,
        'proposed_date' => '2026-11-21',
        'proposed_time' => '11:00',
        'status' => AppointmentChangeRequest::STATUS_PENDING,
    ], $overrides));
}

function phase3d3b2Patch(User $user, string $routeName, AppointmentChangeRequest $request, array $data)
{
    return test()->actingAs($user)->patch(route($routeName, ['changeRequest' => $request->id]), $data);
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3d3b2Freeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

function phase3d3b2NotificationCount(): int
{
    return DB::table('notifications')->count();
}

/** Assert a refusal wrote nothing: schedule, request state and audits. */
function phase3d3b2ExpectNoWrites(Appointment $appointment, AppointmentChangeRequest $request): void
{
    $appointment->refresh();
    $request->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($request->reviewed_by)->toBeNull()
        ->and($request->reviewed_at)->toBeNull()
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(0);
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Approve path
|--------------------------------------------------------------------------
*/

it('approve applies the exact proposed schedule and stamps the request', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');
    $originalReviewer = $appointment->reviewed_by;

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [
        'review_note' => 'Slot confirmed with the patient.',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $appointment->refresh();
    $request->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-20')
        ->and($appointment->confirmed_time)->toBe('10:00')
        ->and($appointment->requested_date?->toDateString())->toBe('2026-11-05')
        ->and($appointment->requested_time)->toBe('10:00')
        ->and($appointment->reviewed_by)->toBe($originalReviewer)
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($request->reviewed_by)->toBe($staff->id)
        ->and($request->reviewed_at)->not->toBeNull()
        ->and($request->review_note)->toBe('Slot confirmed with the patient.');

    expect(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->first()->patient_id)->toBe($patient->id)
        // Step 3: the SINGLE patient outcome notification - no generic
        // status-changed row is added alongside it.
        ->and(phase3d3b2NotificationCount())->toBe(1)
        ->and(DB::table('notifications')
            ->where('type', AppointmentRescheduleApprovedNotification::class)->count())->toBe(1);
});

it('approve accepts an empty optional staff note', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, ['review_note' => ''])
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($request->review_note)->toBeNull()
        ->and($appointment->refresh()->confirmed_date?->toDateString())->toBe('2026-11-20');
});

it('approve ignores any schedule the reviewer posts - the proposal cannot be edited (K4)', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [
        'proposed_date' => '2027-05-05',
        'proposed_time' => '07:00',
        'confirmed_date' => '2027-06-06',
        'confirmed_time' => '23:00',
        'status' => Appointment::STATUS_CANCELLED,
        'review_note' => 'Approved with my own adjustments.',
    ])->assertSessionHasNoErrors();

    $appointment->refresh();

    expect($appointment->confirmed_date?->toDateString())->toBe('2026-11-20')
        ->and($appointment->confirmed_time)->toBe('10:00')
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($request->refresh()->review_note)->toBe('Approved with my own adjustments.');
});

it('approve writes an audit row carrying the old and new schedules', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [
        'review_note' => 'Capacity confirmed.',
    ])->assertSessionHasNoErrors();

    $audit = AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED');

    expect($audit->count())->toBe(1)
        ->and($audit->first()->user_id)->toBe($staff->id)
        ->and($audit->first()->patient_id)->toBe($patient->id)
        ->and($audit->first()->module)->toBe('APPOINTMENT')
        ->and($audit->first()->description)
            ->toContain('2026-11-07')
            ->toContain('14:00')
            ->toContain('2026-11-20')
            ->toContain('10:00')
            ->toContain('Capacity confirmed.');
});

it('approve applies delivery proposals date-only with confirmed_time NULL (K5)', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $portalUser = User::query()->find($patient->user_id);
    $clean = phase3d3b2DeliveryAppointment($patient);
    $stray = phase3d3b2DeliveryAppointment($patient);
    $staff = phase3d3b2User('staff');

    // Real patient path: date-only proposal.
    $cleanRequest = app(AppointmentManagementService::class)
        ->requestAppointmentReschedule($clean, $patient, $portalUser, '2026-11-25', null, null);

    // Forged/stray row carrying a time on a delivery request.
    $strayRequest = phase3d3b2FixtureRequest($stray, [
        'proposed_date' => '2026-11-26',
        'proposed_time' => '15:00',
    ]);

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $cleanRequest, [])
        ->assertSessionHasNoErrors();
    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $strayRequest, [])
        ->assertSessionHasNoErrors();

    $clean->refresh();
    $stray->refresh();

    expect($clean->confirmed_date?->toDateString())->toBe('2026-11-25')
        ->and($clean->confirmed_time)->toBeNull()
        ->and($stray->confirmed_date?->toDateString())->toBe('2026-11-26')
        ->and($stray->confirmed_time)->toBeNull()
        ->and($strayRequest->refresh()->proposed_time)->toBe('15:00')
        ->and($clean->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($stray->status)->toBe(Appointment::STATUS_CONFIRMED);
});

/*
|--------------------------------------------------------------------------
| Review-time revalidation (approved rules)
|--------------------------------------------------------------------------
*/

it('approve refuses an exact duplicate active appointment that appeared after submission', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');

    // A conflicting appointment is created BETWEEN submission and review.
    phase3d3b2ConfirmedAppointment($patient, [
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => '10:00',
    ]);

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    phase3d3b2ExpectNoWrites($appointment, $request);
    expect(phase3d3b2NotificationCount())->toBe(0);
});

it('approve refuses once the proposed schedule is in the past', function () {
    // Current schedule stays FUTURE at review time so only the
    // proposed-schedule rule can fire.
    phase3d3b2Freeze('2026-11-01 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient, [
        'confirmed_date' => '2026-12-01',
        'confirmed_time' => '09:00',
    ]);
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');

    phase3d3b2Freeze('2026-11-21 09:00:00'); // proposed 11-20 is now past.

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    $appointment->refresh();
    $request->refresh();

    expect($appointment->confirmed_date?->toDateString())->toBe('2026-12-01')
        ->and($appointment->confirmed_time)->toBe('09:00')
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0);
});

it('approve refuses once the current appointment cutoff has passed', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient); // confirmed 2026-11-07 14:00
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');

    // Proposed schedule is still FUTURE at review - only the current
    // appointment cutoff can fire.
    phase3d3b2Freeze('2026-11-07 14:00:00');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    phase3d3b2ExpectNoWrites($appointment, $request);
});

it('approve refuses a proposal identical to the current confirmed schedule', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2FixtureRequest($appointment, [
        'proposed_date' => '2026-11-07',
        'proposed_time' => '14:00',
    ]);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    phase3d3b2ExpectNoWrites($appointment, $request);
});

/*
|--------------------------------------------------------------------------
| Decline path
|--------------------------------------------------------------------------
*/

it('decline keeps the original confirmed schedule and stamps the request', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');
    $originalReviewer = $appointment->reviewed_by;

    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'No available slot that day.',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $appointment->refresh();
    $request->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($appointment->reviewed_by)->toBe($originalReviewer)
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and($request->reviewed_by)->toBe($staff->id)
        ->and($request->reviewed_at)->not->toBeNull()
        ->and($request->review_note)->toBe('No available slot that day.');

    expect(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0)
        // Step 3: the SINGLE patient outcome notification for the decline.
        ->and(phase3d3b2NotificationCount())->toBe(1)
        ->and(DB::table('notifications')
            ->where('type', AppointmentRescheduleDeclinedNotification::class)->count())->toBe(1);
});

it('decline requires an explanation', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [])
        ->assertSessionHasErrors('review_note');
    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, ['review_note' => 'ab'])
        ->assertSessionHasErrors('review_note');

    phase3d3b2ExpectNoWrites($appointment, $request);
});

it('refuses approval of an expired proposal but still allows the decline', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient); // 2026-11-07 14:00
    $request = phase3d3b2RescheduleRequest($appointment, $patient, '2026-11-20', '10:00');
    $staff = phase3d3b2User('staff');

    // Both the current schedule and the proposal are now in the past.
    phase3d3b2Freeze('2026-11-21 10:00:00');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0);

    // Decline is deliberately cutoff-free: the stale request can be
    // cleaned up instead of being stranded as pending forever.
    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'The requested day has already passed.',
    ])->assertSessionHasNoErrors();

    $appointment->refresh();
    $request->refresh();

    expect($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Stale / concurrent staff actions
|--------------------------------------------------------------------------
*/

it('refuses approving an already reviewed request twice', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasNoErrors();
    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($appointment->refresh()->confirmed_date?->toDateString())->toBe('2026-11-20')
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(1)
        // The refused second attempt adds no second notification row.
        ->and(phase3d3b2NotificationCount())->toBe(1);
});

it('refuses declining after the request was approved', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [
        'review_note' => 'Approved first.',
    ])->assertSessionHasNoErrors();
    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Trying to decline a finished review.',
    ])->assertSessionHasErrors('change_request');

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        ->and($request->review_note)->toBe('Approved first.')
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(0);
});

it('refuses approving after the request was declined', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Declined first.',
    ])->assertSessionHasNoErrors();
    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    $appointment->refresh();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0);
});

it('refuses a stale approve after staff cancelled the appointment directly (auto-declined in-tx)', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient);
    $staff = phase3d3b2User('staff');

    $this->actingAs($staff)->patch(route('appointments.cancel', $appointment), [
        'cancellation_reason' => 'Clinic closed that day.',
    ])->assertSessionHasNoErrors();

    // Auto-resolution ran in the same transaction as the staff cancel.
    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and($request->review_note)->toContain('Declined automatically');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');

    expect(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'CHANGE_REQUEST_AUTO_DECLINED')->count())->toBe(1)
        ->and($appointment->refresh()->status)->toBe(Appointment::STATUS_CANCELLED);
});

it('refuses a stale decline after staff completed the appointment (auto-declined in-tx)', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2RescheduleRequest($appointment, $patient);
    $staff = phase3d3b2User('staff');

    $this->actingAs($staff)->patch(route('appointments.complete', $appointment), [])
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED);

    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Trying to decline a finished review.',
    ])->assertSessionHasErrors('change_request');

    expect(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'CHANGE_REQUEST_AUTO_DECLINED')->count())->toBe(1)
        ->and($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

/*
|--------------------------------------------------------------------------
| Eligibility boundaries
|--------------------------------------------------------------------------
*/

it('refuses both reviews when the appointment is cancelled or completed', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $cancelled = phase3d3b2ConfirmedAppointment($patient, ['status' => Appointment::STATUS_CANCELLED]);
    $completed = phase3d3b2ConfirmedAppointment($patient, ['status' => Appointment::STATUS_COMPLETED]);
    $cancelledRequest = phase3d3b2FixtureRequest($cancelled);
    $completedRequest = phase3d3b2FixtureRequest($completed);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $cancelledRequest, [])
        ->assertSessionHasErrors('change_request');
    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $cancelledRequest, [
        'review_note' => 'Cleaning up a stale row.',
    ])->assertSessionHasErrors('change_request');
    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $completedRequest, [])
        ->assertSessionHasErrors('change_request');

    expect($cancelledRequest->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($completedRequest->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(0);
});

it('refuses both reviews against a walk-in appointment', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $walkIn = phase3d3b2ConfirmedAppointment($patient, ['source' => Appointment::SOURCE_WALK_IN]);
    $request = phase3d3b2FixtureRequest($walkIn);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHasErrors('change_request');
    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Walk-in rows are not reviewed here.',
    ])->assertSessionHasErrors('change_request');

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($walkIn->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(0);
});

it('rejects an unknown request type safely through both endpoints', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $request = phase3d3b2FixtureRequest($appointment, ['type' => 'transport']);
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [])
        ->assertRedirect()->assertSessionHasErrors('change_request');
    phase3d3b2Patch($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'Unknown type attempt.',
    ])->assertRedirect()->assertSessionHasErrors('change_request');

    expect($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($request->type)->toBe('transport')
        ->and($appointment->refresh()->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_DECLINED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'CANCELLATION_REQUEST_APPROVED')->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Cancellation dispatch regression (same endpoints)
|--------------------------------------------------------------------------
*/

it('still routes cancellation-type requests through the original cancellation review', function () {
    phase3d3b2Freeze('2026-10-12 09:00:00');
    [, $patient] = phase3d3b2Eligible();
    $appointment = phase3d3b2ConfirmedAppointment($patient);
    $portalUser = User::query()->find($patient->user_id);
    $request = app(AppointmentManagementService::class)
        ->requestAppointmentCancellation($appointment, $patient, $portalUser, 'Travelling, please cancel.');
    $staff = phase3d3b2User('staff');

    phase3d3b2Patch($staff, 'appointment-change-requests.approve', $request, [
        'review_note' => 'Confirmed with patient.',
    ])->assertSessionHasNoErrors();

    $appointment->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->cancellation_reason)->toBe('Travelling, please cancel.')
        ->and($request->refresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED)
        // Existing cancellation notification behaviour preserved
        // through the type dispatcher.
        ->and(DB::table('notifications')->where('type', AppointmentCancellationApprovedNotification::class)->count())->toBe(1)
        ->and(AuditLog::where('action', 'CANCELLATION_REQUEST_APPROVED')->count())->toBe(1)
        ->and(AuditLog::where('action', 'RESCHEDULE_REQUEST_APPROVED')->count())->toBe(0);
});
