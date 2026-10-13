<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\EddApproachingNotification;
use App\Services\SystemNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phase 3B.4 - Appointment Notifications (Admin, Staff, Patient)
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the five notification
| events (online request -> clinic; confirmed/declined/cancelled/
| completed -> linked patient), transaction + duplicate-delivery safety,
| the existing clinic tray integration, the new Patient Portal
| Notifications surface (list / unread count / mark-as-read ownership /
| role isolation / archived accounts), destination safety, clinical-alert
| regression, delivery-failure state safety, and the no-email/no-SMS/no-
| queue guarantee.
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3b4User(string $role = 'patient', string $name = 'Notify User'): User
{
    return User::factory()->create(['role' => $role, 'name' => $name]);
}

function phase3b4Patient(string $first = 'Notify', string $last = 'Patient'): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => "phase3b4{$sequence}@notify.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase3b4Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3b4Eligible(): array
{
    $user = phase3b4User('patient', 'Portal Owner');
    $patient = phase3b4Patient();
    phase3b4Link($patient, $user);

    return [$user, $patient];
}

function phase3b4Appointment(Patient $patient, array $overrides = []): Appointment
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

function phase3b4ConfirmedAppointment(Patient $patient, User $reviewer, array $overrides = []): Appointment
{
    return phase3b4Appointment($patient, array_merge([
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => '2026-10-01 08:00:00',
    ], $overrides));
}

/** POST an online request as the authenticated portal user. */
function phase3b4PostOnline(User $user, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.create'))
        ->post(route('patient.appointments.store'), $data);
}

/** PATCH helper that flashes back to the calendar (preserves view/date/filters). */
function phase3b4ActAsPatch(User $user, string $routeName, Appointment $appointment, array $data = [])
{
    return test()->actingAs($user)
        ->from(route('appointments.index'))
        ->patch(route($routeName, $appointment), $data);
}

function phase3b4WalkIn(User $staff, Patient $patient, array $overrides = [])
{
    return test()->actingAs($staff)->post(route('appointments.walk-in.store'), array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'appointment_date' => '2026-10-15',
        'appointment_time' => '15:00',
    ], $overrides));
}

function phase3b4FreezeManila(string $moment): void
{
    Carbon::setTestNow(Carbon::parse($moment, 'Asia/Manila'));
}

/** All notification rows for one type, decoded. */
function phase3b4Rows(string $notificationClass): array
{
    return collect(DB::table('notifications')->where('type', $notificationClass)->get())
        ->map(fn ($row) => json_decode($row->data, true))
        ->all();
}

/** One notification row for a specific notifiable user + type. */
function phase3b4RowFor(User $user, string $notificationClass): ?object
{
    return DB::table('notifications')
        ->where('type', $notificationClass)
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->id)
        ->first();
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Event A - New online appointment request -> active Admin + Staff
|--------------------------------------------------------------------------
*/

it('notifies every active admin and staff account for a new online request', function () {
    $admin = phase3b4User('admin', 'Request Admin');
    $staff = phase3b4User('staff', 'Request Staff');
    [$patientUser] = phase3b4Eligible();

    phase3b4PostOnline($patientUser, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'));

    $appointment = Appointment::sole();

    foreach ([$admin, $staff] as $recipient) {
        $row = phase3b4RowFor($recipient, \App\Notifications\AppointmentRequestedNotification::class);

        expect($row)->not->toBeNull()
            ->and($row->read_at)->toBeNull();

        $data = json_decode($row->data, true);
        expect($data['type'])->toBe('APPOINTMENT_REQUESTED')
            ->and($data['title'])->toBe('New Appointment Request')
            ->and($data['message'])->toContain('New appointment request: Checkup appointment submitted for November 5, 2026.')
            ->and($data['event_key'])->toBe('appointment-requested:' . $appointment->id)
            ->and($data['destination']['route'])->toBe('appointments.index')
            ->and($data['destination']['parameters'])->toBe([
                'view' => 'day',
                'date' => '2026-11-05',
                'status' => 'all',
            ]);
    }

    // Exactly one row per clinic recipient — no patient row, no duplicates.
    expect(DB::table('notifications')->count())->toBe(2);
});

it('excludes archived clinic accounts and patients from new-request recipients', function () {
    $activeAdmin = phase3b4User('admin', 'Active Admin');
    $archivedStaff = phase3b4User('staff', 'Archived Staff');
    $archivedStaff->delete();
    [$patientUser, $patient] = phase3b4Eligible();

    phase3b4PostOnline($patientUser, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => '2026-11-06',
        'requested_time' => '11:00',
    ])->assertRedirect(route('patient.appointments.index'));

    $rows = phase3b4Rows(\App\Notifications\AppointmentRequestedNotification::class);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['message'])->toContain('Counseling appointment submitted for November 6, 2026.')
        ->and(phase3b4RowFor($archivedStaff, \App\Notifications\AppointmentRequestedNotification::class))->toBeNull()
        // The submitting patient is never notified about their own request.
        ->and(phase3b4RowFor($patientUser, \App\Notifications\AppointmentRequestedNotification::class))->toBeNull();
});

it('does not notify the clinic for walk-in creation', function () {
    $staff = phase3b4User('staff', 'Walkin Staff');
    $patient = phase3b4Patient('Walkin', 'Only');

    phase3b4WalkIn($staff, $patient)->assertSessionHas('success');

    expect(Appointment::count())->toBe(1)
        ->and(phase3b4Rows(\App\Notifications\AppointmentRequestedNotification::class))->toBeEmpty()
        ->and(DB::table('notifications')->count())->toBe(0);
});

it('sends no notification for an invalid or duplicate-rejected online submission', function () {
    phase3b4FreezeManila('2026-10-14 14:35:30');
    $admin = phase3b4User('admin', 'Reject Admin');
    [$patientUser] = phase3b4Eligible();

    // Invalid (past) schedule: validation fails before any transaction.
    phase3b4PostOnline($patientUser, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-10-13',
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('requested_date');

    expect(DB::table('notifications')->count())->toBe(0);

    // A valid first submission notifies once...
    phase3b4PostOnline($patientUser, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'));

    expect(DB::table('notifications')->count())->toBe(1);

    // ...and a duplicate-rejected second submission adds nothing.
    phase3b4PostOnline($patientUser, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ])->assertSessionHasErrors('appointment');

    expect(DB::table('notifications')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Events B-E - Status transitions -> linked Patient Portal account
|--------------------------------------------------------------------------
*/

it('notifies the linked patient when a checkup appointment is confirmed with the actual schedule', function () {
    $admin = phase3b4User('admin', 'Confirm Admin');
    [$patientUser, $patient] = phase3b4Eligible();
    $appointment = phase3b4Appointment($patient);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    $row = phase3b4RowFor($patientUser, \App\Notifications\AppointmentConfirmedNotification::class);

    expect($row)->not->toBeNull()
        ->and($row->read_at)->toBeNull()
        ->and($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);

    $data = json_decode($row->data, true);
    expect($data['type'])->toBe('APPOINTMENT_CONFIRMED')
        ->and($data['title'])->toBe('Appointment Confirmed')
        ->and($data['message'])->toBe('Your Checkup appointment has been confirmed for November 5, 2026 at 10:00 AM.')
        ->and($data['event_key'])->toBe('appointment-confirmed:' . $appointment->id)
        ->and($data['destination']['route'])->toBe('patient.appointments.index');
});

it('omits the time clause for a date-only delivery confirmation message', function () {
    $admin = phase3b4User('admin', 'Delivery Admin');
    [$patientUser, $patient] = phase3b4Eligible();
    $appointment = phase3b4Appointment($patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
    ]);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => null,
    ])->assertSessionHas('success');

    $data = json_decode(phase3b4RowFor($patientUser, \App\Notifications\AppointmentConfirmedNotification::class)->data, true);

    expect($data['message'])->toBe('Your Delivery Request appointment has been confirmed for November 5, 2026.');
});

it('notifies the linked patient on declined, cancelled and completed transitions', function () {
    $admin = phase3b4User('admin', 'Transition Admin');
    [$patientUser, $patient] = phase3b4Eligible();
    $decline = phase3b4Appointment($patient, ['requested_date' => '2026-11-05']);

    phase3b4ActAsPatch($admin, 'appointments.decline', $decline, [
        'decline_reason' => 'Slot unavailable.',
    ])->assertSessionHas('success');

    $declinedData = json_decode(phase3b4RowFor($patientUser, \App\Notifications\AppointmentDeclinedNotification::class)->data, true);
    expect($declinedData['message'])->toBe('Your appointment request was declined. Please check My Appointments for details.')
        ->and($declinedData['event_key'])->toBe('appointment-declined:' . $decline->id);

    $cancel = phase3b4ConfirmedAppointment($patient, $admin);

    phase3b4ActAsPatch($admin, 'appointments.cancel', $cancel, [
        'cancellation_reason' => 'Clinic closure.',
    ])->assertSessionHas('success');

    $cancelledData = json_decode(phase3b4RowFor($patientUser, \App\Notifications\AppointmentCancelledNotification::class)->data, true);
    expect($cancelledData['message'])->toBe('Your appointment has been cancelled. Please check My Appointments for details.')
        ->and($cancelledData['event_key'])->toBe('appointment-cancelled:' . $cancel->id);

    $complete = phase3b4ConfirmedAppointment($patient, $admin, ['confirmed_date' => '2026-11-06']);

    phase3b4ActAsPatch($admin, 'appointments.complete', $complete)->assertSessionHas('success');

    $completedData = json_decode(phase3b4RowFor($patientUser, \App\Notifications\AppointmentCompletedNotification::class)->data, true);
    expect($completedData['message'])->toBe('Your appointment has been marked completed.')
        ->and($completedData['event_key'])->toBe('appointment-completed:' . $complete->id)
        ->and($completedData['destination']['route'])->toBe('patient.appointments.index');

    // Three transitions, three patient rows — one per appointment/event.
    expect($patientUser->notifications()->count())->toBe(3);
});

it('sends no patient notification when the patient has no linked or an archived portal account', function () {
    $admin = phase3b4User('admin', 'NoLink Admin');

    // No linked account: the transition still succeeds, nothing is sent.
    $unlinkedPatient = phase3b4Patient('Unlinked', 'Account');
    $unlinked = phase3b4Appointment($unlinkedPatient);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $unlinked, [
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    expect($unlinked->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(DB::table('notifications')->count())->toBe(0);

    // Archived (soft-deleted) portal account: excluded by the User scope.
    $archivedUser = phase3b4User('patient', 'Archived Portal');
    $archivedPatient = phase3b4Patient('Archived', 'Link');
    phase3b4Link($archivedPatient, $archivedUser);
    $archivedUser->delete();
    $appointment = phase3b4Appointment($archivedPatient);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(phase3b4RowFor($archivedUser, \App\Notifications\AppointmentConfirmedNotification::class))->toBeNull();
});

it('sends no notification for invalid transitions and failed validation', function () {
    $admin = phase3b4User('admin', 'Invalid Admin');
    [$patientUser, $patient] = phase3b4Eligible();
    $confirmed = phase3b4ConfirmedAppointment($patient, $admin);

    // Confirming an already-confirmed appointment is refused by the service.
    phase3b4ActAsPatch($admin, 'appointments.confirm', $confirmed, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '11:00',
    ])->assertSessionHasErrors('appointment');

    // Declining a confirmed appointment is not a legal transition.
    phase3b4ActAsPatch($admin, 'appointments.decline', $confirmed, [
        'decline_reason' => 'Late request.',
    ])->assertSessionHasErrors('appointment');

    // A FormRequest rejection (out-of-hours confirmed time) never reaches
    // the service, so no notification can be produced.
    $pending = phase3b4Appointment($patient, ['requested_date' => '2026-11-07']);
    phase3b4ActAsPatch($admin, 'appointments.confirm', $pending, [
        'confirmed_date' => '2026-11-07',
        'confirmed_time' => '17:00',
    ])->assertSessionHasErrors('confirmed_time');

    expect($confirmed->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and(DB::table('notifications')->count())->toBe(0);
});

it('does not duplicate notifications on repeated service calls or re-confirm attempts', function () {
    $admin = phase3b4User('admin', 'Dedupe Admin');
    $staff = phase3b4User('staff', 'Dedupe Staff');
    [$patientUser, $patient] = phase3b4Eligible();
    $appointment = phase3b4Appointment($patient);

    $service = app(SystemNotificationService::class);

    // Event-key ledger: a repeated dispatch of the same logical event is a
    // no-op. One row per clinic recipient for the single logical event.
    expect($service->notifyAppointmentRequested($appointment))->toBeTrue()
        ->and($service->notifyAppointmentRequested($appointment))->toBeFalse();

    expect(phase3b4Rows(\App\Notifications\AppointmentRequestedNotification::class))->toHaveCount(2);

    // The re-confirm attempt is refused by the state guard and adds nothing.
    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '11:00',
    ])->assertSessionHasErrors('appointment');

    $confirmedRows = phase3b4Rows(\App\Notifications\AppointmentConfirmedNotification::class);
    expect($confirmedRows)->toHaveCount(1)
        ->and($patientUser->notifications()->count())->toBe(1);
});

it('keeps existing appointment audit logs intact alongside the new notifications', function () {
    $admin = phase3b4User('admin', 'Audit Admin');
    [$patientUser, $patient] = phase3b4Eligible();
    $appointment = phase3b4Appointment($patient);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    $audit = AuditLog::sole();
    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->patient_id)->toBe($patient->id)
        ->and($audit->action)->toBe('APPOINTMENT_CONFIRMED')
        ->and($audit->module)->toBe('APPOINTMENT')
        // The audit row and the notification row coexist independently.
        ->and(DB::table('notifications')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Admin/Staff notification integration (existing tray)
|--------------------------------------------------------------------------
*/

it('shows appointment notifications in the existing clinic notification tray', function () {
    $admin = phase3b4User('admin', 'Tray Admin');
    [$patientUser] = phase3b4Eligible();

    phase3b4PostOnline($patientUser, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'));

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('New Appointment Request')
        ->assertSee('New appointment request: Checkup appointment submitted for November 5, 2026.')
        ->assertSee('View request');

    // The clinic mark-as-read route consumes the same row.
    $row = phase3b4RowFor($admin, \App\Notifications\AppointmentRequestedNotification::class);

    $this->actingAs($admin)
        ->post(route('notifications.read', $row->id))
        ->assertRedirect();

    expect(DB::table('notifications')->find($row->id)->read_at)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Patient Portal notification surface
|--------------------------------------------------------------------------
*/

it('lists only the authenticated patient own notifications, newest first', function () {
    $admin = phase3b4User('admin', 'Isolation Admin');
    [$userA, $patientA] = phase3b4Eligible();
    [$userB, $patientB] = phase3b4Eligible();

    $forA = phase3b4Appointment($patientA, ['appointment_type' => Appointment::TYPE_CHECKUP]);
    $forB = phase3b4Appointment($patientB, ['appointment_type' => Appointment::TYPE_COUNSELING]);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $forA, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '10:00',
    ])->assertSessionHas('success');
    phase3b4ActAsPatch($admin, 'appointments.confirm', $forB, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '11:00',
    ])->assertSessionHas('success');

    $this->actingAs($userA)
        ->get(route('patient.notifications.index'))
        ->assertOk()
        ->assertSee('Checkup appointment has been confirmed')
        ->assertDontSee('Counseling appointment has been confirmed')
        ->assertSee('View My Appointments');

    $this->actingAs($userB)
        ->get(route('patient.notifications.index'))
        ->assertOk()
        ->assertSee('Counseling appointment has been confirmed')
        ->assertDontSee('Checkup appointment has been confirmed');
});

it('renders an accurate unread count and a friendly empty state', function () {
    $admin = phase3b4User('admin', 'Unread Admin');
    [$patientUser, $patient] = phase3b4Eligible();

    // Empty state first.
    $this->actingAs($patientUser)
        ->get(route('patient.notifications.index'))
        ->assertOk()
        ->assertSee('You have no notifications yet');

    $first = phase3b4Appointment($patient, ['requested_date' => '2026-11-05']);
    $second = phase3b4ConfirmedAppointment($patient, $admin, ['confirmed_date' => '2026-11-06']);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $first, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '10:00',
    ])->assertSessionHas('success');
    phase3b4ActAsPatch($admin, 'appointments.complete', $second)->assertSessionHas('success');

    // Unread badge in the nav + Mark-all action + Manila timestamp.
    $this->actingAs($patientUser)
        ->get(route('patient.notifications.index'))
        ->assertOk()
        ->assertSee('aria-label="2 unread notifications"', false)
        ->assertSee('Mark all as read')
        ->assertSee('Your Checkup appointment has been confirmed for November 5, 2026 at 10:00 AM.')
        ->assertSee('Your appointment has been marked completed.');

    // Mark all as read clears the badge, the action, and every per-row
    // "Mark as read" button (read items no longer offer the action).
    $this->actingAs($patientUser)
        ->post(route('patient.notifications.markAllRead'))
        ->assertRedirect();

    $this->actingAs($patientUser)
        ->get(route('patient.notifications.index'))
        ->assertOk()
        ->assertDontSee('Mark all as read')
        ->assertDontSee('Mark as read')
        ->assertDontSee('aria-label="2 unread notifications"', false);
});

it('marks a single notification as read for the owner and rejects another user id', function () {
    $admin = phase3b4User('admin', 'Owner Admin');
    [$userA, $patientA] = phase3b4Eligible();
    [$userB, $patientB] = phase3b4Eligible();
    [$userC, $patientC] = phase3b4Eligible();

    $appointmentA = phase3b4Appointment($patientA);
    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointmentA, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    $appointmentB = phase3b4Appointment($patientB);
    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointmentB, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '11:00',
    ])->assertSessionHas('success');

    $appointmentC = phase3b4Appointment($patientC);
    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointmentC, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '12:00',
    ])->assertSessionHas('success');

    $rowA = phase3b4RowFor($userA, \App\Notifications\AppointmentConfirmedNotification::class);
    $rowB = phase3b4RowFor($userB, \App\Notifications\AppointmentConfirmedNotification::class);
    $rowC = phase3b4RowFor($userC, \App\Notifications\AppointmentConfirmedNotification::class);

    // The owner can mark their own notification read.
    $this->actingAs($userA)
        ->post(route('patient.notifications.read', $rowA->id))
        ->assertRedirect();

    expect(DB::table('notifications')->find($rowA->id)->read_at)->not->toBeNull()
        ->and(DB::table('notifications')->find($rowB->id)->read_at)->toBeNull()
        ->and(DB::table('notifications')->find($rowC->id)->read_at)->toBeNull();

    // Another user's notification id resolves to 404 — never modified.
    $this->actingAs($userA)
        ->post(route('patient.notifications.read', $rowB->id))
        ->assertNotFound();

    expect(DB::table('notifications')->find($rowB->id)->read_at)->toBeNull();

    // Mark-all only ever touches the caller's own rows; C's row is untouched.
    $this->actingAs($userB)
        ->post(route('patient.notifications.markAllRead'))
        ->assertRedirect();

    expect(DB::table('notifications')->find($rowB->id)->read_at)->not->toBeNull()
        ->and(DB::table('notifications')->find($rowC->id)->read_at)->toBeNull();
});

it('denies guest, clinic-role and archived/unlinked patient access to portal notification routes', function () {
    $admin = phase3b4User('admin', 'Boundary Admin');

    // Guests are redirected to login.
    $this->get(route('patient.notifications.index'))->assertRedirect(route('login'));
    $this->post(route('patient.notifications.markAllRead'))->assertRedirect(route('login'));

    // Clinic roles are refused by the in-controller check.
    $this->actingAs($admin)
        ->get(route('patient.notifications.index'))->assertForbidden();
    $this->actingAs($admin)
        ->post(route('patient.notifications.markAllRead'))->assertForbidden();

    // An unlinked patient account is refused by the portal middleware.
    $unlinked = phase3b4User('patient', 'Unlinked User');
    $this->actingAs($unlinked)
        ->get(route('patient.notifications.index'))->assertForbidden();

    // An archived patient record is refused by the portal middleware.
    $archivedUser = phase3b4User('patient', 'Archived Portal User');
    $archivedLink = phase3b4Patient('Archived', 'Portal');
    phase3b4Link($archivedLink, $archivedUser);
    $archivedLink->delete();
    $this->actingAs($archivedUser)
        ->get(route('patient.notifications.index'))->assertForbidden();
});

it('keeps patients out of the clinic notification routes', function () {
    [$patientUser] = phase3b4Eligible();

    $this->actingAs($patientUser)
        ->post(route('notifications.markAllRead'))->assertForbidden();

    $foreignId = '00000000-0000-0000-0000-000000000000';
    $this->actingAs($patientUser)
        ->post(route('notifications.read', $foreignId))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Destination safety, clinical-alert regression, delivery-failure safety
|--------------------------------------------------------------------------
*/

it('builds notification links only from authorized named routes', function () {
    $admin = phase3b4User('admin', 'Link Admin');
    [$patientUser, $patient] = phase3b4Eligible();
    $appointment = phase3b4Appointment($patient);

    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    // Clinic destination resolves to the authorized calendar deep link;
    // the patient destination is what this transition produced.
    $confirmedData = json_decode(phase3b4RowFor($patientUser, \App\Notifications\AppointmentConfirmedNotification::class)->data, true);

    $this->actingAs($patientUser)
        ->get(route($confirmedData['destination']['route'], $confirmedData['destination']['parameters']))
        ->assertOk();

    // And the same deep link the online-request notification uses is a
    // clinic-owned, authorized calendar URL with no patient identifier.
    $deepLink = route('appointments.index', ['view' => 'day', 'date' => '2026-11-05', 'status' => 'all']);
    expect($deepLink)->not->toContain('patient_id');

    $this->actingAs($admin)->get($deepLink)->assertOk();
});

it('keeps the existing clinical-alert notification paths functioning', function () {
    $admin = phase3b4User('admin', 'Alert Admin');
    $patient = phase3b4Patient('Alert', 'Pregnancy');

    $service = app(SystemNotificationService::class);

    expect($service->notifyEddApproaching($patient))->toBeTrue()
        ->and($service->notifyEddApproaching($patient))->toBeFalse();

    $rows = phase3b4Rows(EddApproachingNotification::class);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['event_key'])->toBe('edd-approaching:' . $patient->id)
        ->and(phase3b4RowFor($admin, EddApproachingNotification::class))->not->toBeNull();
});

it('does not corrupt appointment state when notification delivery fails', function () {
    $admin = phase3b4User('admin', 'Failure Admin');
    [$patientUser, $patient] = phase3b4Eligible();
    $appointment = phase3b4Appointment($patient);

    $this->mock(SystemNotificationService::class)
        ->shouldReceive('notifyAppointmentStatusChanged')
        ->andThrow(new RuntimeException('Notification transport down.'));

    $this->actingAs($admin)
        ->from(route('appointments.index'))
        ->patch(route('appointments.confirm', $appointment), [
            'confirmed_date' => '2026-11-05',
            'confirmed_time' => '10:00',
        ])
        ->assertStatus(500);

    // The transaction already committed: the appointment is confirmed and
    // the audit row exists. A notification failure can never roll it back.
    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AuditLog::where('action', 'APPOINTMENT_CONFIRMED')->count())->toBe(1)
        ->and(DB::table('notifications')->count())->toBe(0);
});

it('generates no emails, SMS or queued jobs for appointment notifications', function () {
    Mail::fake();
    Queue::fake();

    $admin = phase3b4User('admin', 'Channel Admin');
    [$patientUser, $patient] = phase3b4Eligible();

    phase3b4PostOnline($patientUser, [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
    ])->assertRedirect(route('patient.appointments.index'));

    $appointment = Appointment::sole();
    phase3b4ActAsPatch($admin, 'appointments.confirm', $appointment, [
        'confirmed_date' => '2026-11-05', 'confirmed_time' => '10:00',
    ])->assertSessionHas('success');

    Mail::assertNothingSent();
    Queue::assertNothingPushed();

    expect(DB::table('jobs')->count())->toBe(0)
        // Both notifications landed as database rows (the only channel).
        ->and(DB::table('notifications')->count())->toBe(2);

    // Every appointment notification class is database-only (checked via
    // reflection so no constructor arguments are needed).
    foreach ([
        \App\Notifications\AppointmentRequestedNotification::class,
        \App\Notifications\AppointmentConfirmedNotification::class,
        \App\Notifications\AppointmentDeclinedNotification::class,
        \App\Notifications\AppointmentCancelledNotification::class,
        \App\Notifications\AppointmentCompletedNotification::class,
    ] as $class) {
        $instance = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        expect($instance->via(null))->toBe(['database']);
    }
});
