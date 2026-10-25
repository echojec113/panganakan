<?php

use App\Models\Appointment;
use App\Models\AppointmentSlotHold;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentBookedNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentRequestedNotification;
use App\Services\AppointmentManagementService;
use App\Services\ClinicAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| FCFS Phase 2 - automatic confirmation of NEW online CHECKUP bookings
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Slot-collision tests here are
| SEQUENTIAL / SIMULATED (the second booking runs after the first commit,
| or the occupancy pre-check is stubbed so the UNIQUE hold index is the
| only barrier). They do NOT prove true multi-connection concurrency.
*/

function fcfsUser(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

/** @return array{0: User, 1: Patient} */
function fcfsPortal(): array
{
    static $sequence = 0;
    $sequence++;

    $user = fcfsUser('patient');
    $patient = Patient::create([
        'first_name' => 'Fcfs' . $sequence,
        'last_name' => 'Patient',
        'age' => 28,
        'email' => "fcfs{$sequence}@portal.test",
        'contact_number' => '09171234567',
        'address_line' => '123 Mabini Street',
        'barangay' => 'Sampaloc',
        'city_municipality' => 'Manila',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
    $patient->user_id = $user->id;
    $patient->save();

    return [$user, $patient];
}

function fcfsDate(int $daysAhead = 7): string
{
    return Carbon::now('Asia/Manila')->startOfDay()->addDays($daysAhead)->toDateString();
}

function fcfsPost(User $user, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.create'))
        ->post(route('patient.appointments.store'), $data);
}

function fcfsCheckup(string $date, string $time = '10:00'): array
{
    return [
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => $date,
        'requested_time' => $time,
    ];
}

function fcfsService(): AppointmentManagementService
{
    return app(AppointmentManagementService::class);
}

afterEach(function () {
    Carbon::setTestNow();
});

it('confirms a new online checkup immediately', function () {
    [$user, $patient] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($user, fcfsCheckup($date))
        ->assertRedirect(route('patient.appointments.index'))
        ->assertSessionHas('success', 'Your checkup is confirmed. Your selected time slot is now reserved for you.');

    $appointment = Appointment::sole();
    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->source)->toBe(Appointment::SOURCE_ONLINE)
        ->and($appointment->appointment_type)->toBe(Appointment::TYPE_CHECKUP)
        ->and($appointment->patient_id)->toBe($patient->id);
});

it('keeps requested and confirmed schedules identical', function () {
    [$user] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($user, fcfsCheckup($date, '14:30'));

    $appointment = Appointment::sole();
    expect($appointment->requested_date->toDateString())->toBe($date)
        ->and($appointment->confirmed_date->toDateString())->toBe($date)
        ->and(substr((string) $appointment->requested_time, 0, 5))->toBe('14:30')
        ->and(substr((string) $appointment->confirmed_time, 0, 5))->toBe('14:30');
});

it('creates the slot hold for the booked slot', function () {
    [$user] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($user, fcfsCheckup($date));

    $appointment = Appointment::sole();
    $hold = AppointmentSlotHold::sole();
    expect($hold->appointment_id)->toBe($appointment->id)
        ->and(substr((string) $hold->hold_date, 0, 10))->toBe($date)
        ->and(substr((string) $hold->slot_start, 0, 5))->toBe('10:00');
});

it('leaves reviewed_by and reviewed_at NULL for a system-confirmed booking', function () {
    [$user] = fcfsPortal();

    fcfsPost($user, fcfsCheckup(fcfsDate()));

    $appointment = Appointment::sole();
    expect($appointment->reviewed_by)->toBeNull()
        ->and($appointment->reviewed_at)->toBeNull();
});

it('records the ONLINE_APPOINTMENT_AUTO_CONFIRMED audit with the portal user as actor', function () {
    [$user, $patient] = fcfsPortal();

    fcfsPost($user, fcfsCheckup(fcfsDate()));

    $audit = AuditLog::sole();
    expect($audit->action)->toBe('ONLINE_APPOINTMENT_AUTO_CONFIRMED')
        ->and($audit->user_id)->toBe($user->id)
        ->and($audit->patient_id)->toBe($patient->id)
        ->and($audit->module)->toBe('APPOINTMENT');
});

it('notifies the patient and every active clinic user after a successful booking', function () {
    $admin = fcfsUser('admin');
    $staff = fcfsUser('staff');
    [$user] = fcfsPortal();

    fcfsPost($user, fcfsCheckup(fcfsDate()));

    $appointment = Appointment::sole();

    foreach ([$admin, $staff] as $clinicUser) {
        $row = DB::table('notifications')
            ->where('type', AppointmentBookedNotification::class)
            ->where('notifiable_id', $clinicUser->id)
            ->first();
        expect($row)->not->toBeNull();
        $data = json_decode($row->data, true);
        expect($data['type'])->toBe('APPOINTMENT_BOOKED')
            ->and($data['event_key'])->toBe('appointment-booked:' . $appointment->id);
    }

    $patientRow = DB::table('notifications')
        ->where('type', AppointmentConfirmedNotification::class)
        ->where('notifiable_id', $user->id)
        ->first();
    expect($patientRow)->not->toBeNull()
        ->and(json_decode($patientRow->data, true)['message'])->toContain('has been confirmed')
        // No "request" alert and no email/queue channel for a booking.
        ->and(DB::table('notifications')->where('type', AppointmentRequestedNotification::class)->count())->toBe(0)
        ->and(DB::table('notifications')->count())->toBe(3);
});

it('does not duplicate booking notifications when dispatch is retried', function () {
    fcfsUser('admin');
    [$user] = fcfsPortal();

    fcfsPost($user, fcfsCheckup(fcfsDate()));
    $appointment = Appointment::sole();
    $notifications = app(\App\Services\SystemNotificationService::class);

    expect($notifications->notifyAppointmentBooked($appointment))->toBeFalse()
        ->and($notifications->notifyAppointmentStatusChanged($appointment, Appointment::STATUS_CONFIRMED))->toBeFalse()
        ->and(DB::table('notifications')->count())->toBe(2);
});

it('produces no success notifications, audit, appointment or hold for a failed booking', function () {
    fcfsUser('admin');
    [$userA] = fcfsPortal();
    [$userB] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($userA, fcfsCheckup($date))->assertSessionHas('success');

    $notificationsBefore = DB::table('notifications')->count();

    fcfsPost($userB, fcfsCheckup($date))
        ->assertSessionHasErrors('appointment')
        ->assertSessionMissing('success');

    expect(DB::table('notifications')->count())->toBe($notificationsBefore)
        ->and(AuditLog::count())->toBe(1)
        ->and(Appointment::count())->toBe(1)
        ->and(AppointmentSlotHold::count())->toBe(1);
});

it('rejects a second patient for the same slot without orphan records (sequential collision)', function () {
    [$userA, $patientA] = fcfsPortal();
    [$userB, $patientB] = fcfsPortal();
    $date = fcfsDate();

    fcfsService()->createOnlineRequest($patientA, Appointment::TYPE_CHECKUP, $date, '10:00', $userA);

    expect(fn () => fcfsService()->createOnlineRequest($patientB, Appointment::TYPE_CHECKUP, $date, '10:00', $userB))
        ->toThrow(DomainException::class);

    expect(Appointment::count())->toBe(1)
        ->and(AppointmentSlotHold::count())->toBe(1)
        ->and(Appointment::where('patient_id', $patientB->id)->exists())->toBeFalse();
});

it('rolls back the appointment and audit when the UNIQUE hold index is the only barrier (simulated race)', function () {
    [$userA, $patientA] = fcfsPortal();
    [$userB, $patientB] = fcfsPortal();
    $date = fcfsDate();

    fcfsService()->createOnlineRequest($patientA, Appointment::TYPE_CHECKUP, $date, '10:00', $userA);

    // Simulate the race loser: its occupancy pre-check saw a free slot
    // (stale read), so only the UNIQUE (hold_date, slot_start) index stops it.
    $this->partialMock(ClinicAvailabilityService::class, function ($mock) {
        $mock->shouldReceive('isSlotOccupied')->andReturn(false);
    });

    $caught = null;
    try {
        app(AppointmentManagementService::class)
            ->createOnlineRequest($patientB, Appointment::TYPE_CHECKUP, $date, '10:00', $userB);
    } catch (\Throwable $e) {
        $caught = $e;
    }

    expect($caught)->toBeInstanceOf(DomainException::class)
        ->and(Appointment::count())->toBe(1)
        ->and(AppointmentSlotHold::count())->toBe(1)
        ->and(AuditLog::count())->toBe(1);
});

it('rejects a same-patient duplicate booking of the same slot', function () {
    [$user, $patient] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($user, fcfsCheckup($date))->assertSessionHas('success');
    fcfsPost($user, fcfsCheckup($date))->assertSessionHasErrors('appointment');

    expect(Appointment::where('patient_id', $patient->id)->count())->toBe(1)
        ->and(AppointmentSlotHold::count())->toBe(1);
});

it('refuses to auto-confirm for a user who does not own the patient record', function () {
    [, $patient] = fcfsPortal();
    $other = fcfsUser('patient');

    expect(fn () => fcfsService()->createOnlineRequest($patient, Appointment::TYPE_CHECKUP, fcfsDate(), '10:00', $other))
        ->toThrow(DomainException::class);

    expect(Appointment::count())->toBe(0);
});

it('releases the slot when a confirmed online booking is cancelled', function () {
    [$userA, $patientA] = fcfsPortal();
    [$userB] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($userA, fcfsCheckup($date));
    $appointment = Appointment::sole();

    fcfsService()->cancel($appointment, fcfsUser('admin'), 'Patient request');

    expect(AppointmentSlotHold::count())->toBe(0);

    fcfsPost($userB, fcfsCheckup($date))->assertSessionHas('success');
    expect(Appointment::where('status', Appointment::STATUS_CONFIRMED)->count())->toBe(1);
});

it('retains slot occupancy after a booking is completed', function () {
    [$userA, $patientA] = fcfsPortal();
    [$userB] = fcfsPortal();
    $date = fcfsDate(2);

    fcfsPost($userA, fcfsCheckup($date, '10:00'));
    $appointment = Appointment::sole();

    Carbon::setTestNow(Carbon::parse($date . ' 18:00', 'Asia/Manila'));
    fcfsService()->complete($appointment, fcfsUser('staff'));
    Carbon::setTestNow(Carbon::parse($date . ' 18:00', 'Asia/Manila')->subDays(3));

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_COMPLETED)
        ->and(AppointmentSlotHold::count())->toBe(1);

    fcfsPost($userB, fcfsCheckup($date, '10:00'))->assertSessionHasErrors('appointment');
});

it('keeps counseling requests pending with no hold', function () {
    fcfsUser('admin');
    [$user] = fcfsPortal();

    fcfsPost($user, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => fcfsDate(),
        'requested_time' => '11:00',
    ])->assertSessionHas('success', 'Your appointment request has been submitted. Please wait for confirmation from the clinic.');

    $appointment = Appointment::sole();
    expect($appointment->status)->toBe(Appointment::STATUS_PENDING)
        ->and($appointment->confirmed_date)->toBeNull()
        ->and(AppointmentSlotHold::count())->toBe(0)
        ->and(DB::table('notifications')->where('type', AppointmentRequestedNotification::class)->count())->toBe(1)
        ->and(DB::table('notifications')->where('type', AppointmentBookedNotification::class)->count())->toBe(0);
});

it('keeps delivery requests pending and date-only capable', function () {
    [$user] = fcfsPortal();

    fcfsPost($user, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => fcfsDate(),
    ])->assertRedirect(route('patient.appointments.index'));

    $appointment = Appointment::sole();
    expect($appointment->status)->toBe(Appointment::STATUS_PENDING)
        ->and($appointment->requested_time)->toBeNull()
        ->and(AppointmentSlotHold::count())->toBe(0);
});

it('leaves staff walk-in booking unchanged', function () {
    [, $patient] = fcfsPortal();
    $staff = fcfsUser('staff');
    $date = fcfsDate();

    $appointment = fcfsService()->createWalkIn($staff, $patient->id, Appointment::TYPE_CHECKUP, $date, '09:00');

    expect($appointment->source)->toBe(Appointment::SOURCE_WALK_IN)
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AppointmentSlotHold::count())->toBe(1)
        ->and(AuditLog::where('action', 'ONLINE_APPOINTMENT_AUTO_CONFIRMED')->count())->toBe(0);
});

it('does not auto-convert existing pending requests', function () {
    [$user, $patient] = fcfsPortal();
    $date = fcfsDate();

    $pending = Appointment::forceCreate([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => $date,
        'requested_time' => '13:00',
        'status' => Appointment::STATUS_PENDING,
    ]);

    fcfsPost($user, fcfsCheckup($date, '15:00'))->assertSessionHas('success');

    expect($pending->fresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($pending->fresh()->confirmed_date)->toBeNull()
        ->and(AppointmentSlotHold::where('appointment_id', $pending->id)->exists())->toBeFalse();
});

it('still requires staff approval for a patient reschedule', function () {
    [$user, $patient] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($user, fcfsCheckup($date, '10:00'));
    $appointment = Appointment::sole();
    $newDate = fcfsDate(9);

    $request = fcfsService()->requestAppointmentReschedule($appointment, $patient, $user, $newDate, '11:00', 'Conflict');

    $appointment->refresh();
    expect($request->status)->toBe('pending')
        ->and($appointment->confirmed_date->toDateString())->toBe($date)
        ->and(substr((string) $appointment->confirmed_time, 0, 5))->toBe('10:00')
        ->and(substr((string) AppointmentSlotHold::sole()->hold_date, 0, 10))->toBe($date);
});

it('shows type-aware booking copy on the patient pages', function () {
    [$user] = fcfsPortal();

    $this->actingAs($user)->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee('Checkups are confirmed immediately')
        ->assertSee('Counseling and delivery requests are reviewed by the clinic')
        ->assertDontSee('Submitting an appointment request does not guarantee confirmation.');

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Checkups are confirmed immediately');
});

it('shows the auto-confirmed booking as confirmed on the staff calendar', function () {
    [$user, $patient] = fcfsPortal();
    $date = fcfsDate();

    fcfsPost($user, fcfsCheckup($date));

    $this->actingAs(fcfsUser('admin'))
        ->get(route('appointments.index', ['view' => 'day', 'date' => $date]))
        ->assertOk()
        ->assertSee($patient->last_name)
        ->assertSee('Confirmed');
});

it('keeps the Phase 1 slot-picker watchers and deduplicated emission in place', function () {
    $source = file_get_contents(resource_path('views/components/slot-picker.blade.php'));

    expect($source)
        ->toContain("\$watch('selected'")
        ->toContain("\$watch('loading'")
        ->toContain('lastEmittedReady === ready');
});
