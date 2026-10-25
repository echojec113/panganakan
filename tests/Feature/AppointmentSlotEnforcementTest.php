<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\AppointmentSlotHold;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentManagementService;
use App\Services\ClinicAvailabilityService;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Phase 2 - Backend enforcement of slot grid, closures and occupancy
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the five appointment
| write paths (confirm, walk-in, online request, reschedule submission,
| reschedule approval) plus cancel/decline/complete hold lifecycle:
| slot-grid alignment, clinic closure enforcement, occupancy rejection,
| the hold ledger (reserve / move / release / retain), legacy
| compatibility (non-grid pending confirmation refusal, non-grid
| proposal refusal, legacy-confirmed reschedule without a hold) and
| single-transaction rollback when the slot is lost. Frozen-time tests
| reset in afterEach.
*/

function asfService(): AppointmentManagementService
{
    return app(AppointmentManagementService::class);
}

function asfAvailability(): ClinicAvailabilityService
{
    return app(ClinicAvailabilityService::class);
}

function asfAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Enforce Admin']);
}

function asfStaff(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Enforce Staff']);
}

function asfPatient(string $first = 'Anna', string $last = 'Cruz'): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => "asf{$sequence}@enforce.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function asfPending(Patient $patient, array $overrides = []): Appointment
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

function asfConfirmed(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
        'status' => Appointment::STATUS_CONFIRMED,
        'reviewed_by' => asfAdmin()->id,
        'reviewed_at' => now(),
    ], $overrides));
}

function asfPortalPair(): array
{
    $user = User::factory()->create(['role' => 'patient', 'name' => 'Enforce Patient']);
    $patient = asfPatient();
    $patient->user_id = $user->id;
    $patient->save();

    return [$user, $patient];
}

function asfHoldExists(string $date, string $slot): bool
{
    return AppointmentSlotHold::query()
        ->where('hold_date', $date)
        ->where('slot_start', $slot)
        ->exists();
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 1. Slot-grid alignment on every write path
|--------------------------------------------------------------------------
*/

it('refuses a confirmation at a non-grid time', function () {
    $appointment = asfPending(asfPatient());

    expect(fn () => asfService()->confirm($appointment, asfAdmin(), '2026-11-06', '09:17'))
        ->toThrow(DomainException::class);
});

it('refuses a confirmation at 17:00 (clinic close is exclusive)', function () {
    $appointment = asfPending(asfPatient());

    expect(fn () => asfService()->confirm($appointment, asfAdmin(), '2026-11-06', '17:00'))
        ->toThrow(DomainException::class);
});

it('accepts the final 16:30 slot and reserves its hold', function () {
    $appointment = asfPending(asfPatient());

    $confirmed = asfService()->confirm($appointment, asfAdmin(), '2026-11-06', '16:30');

    expect($confirmed->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(asfHoldExists('2026-11-06', '16:30'))->toBeTrue();
});

it('refuses a walk-in at a non-grid time through the FormRequest', function () {
    $staff = asfStaff();
    $patient = asfPatient();

    $this->actingAs($staff)
        ->from(route('appointments.index'))
        ->post(route('appointments.walk-in.store'), [
            'patient_id' => $patient->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'appointment_date' => now('Asia/Manila')->addDays(30)->toDateString(),
            'appointment_time' => '09:45',
        ])
        ->assertRedirect(route('appointments.index'))
        ->assertSessionHasErrors('appointment_time');

    expect(Appointment::query()->count())->toBe(0);
});

it('refuses a patient online request at a non-grid time through the FormRequest', function () {
    [$user] = asfPortalPair();

    $this->actingAs($user)
        ->from(route('patient.appointments.create'))
        ->post(route('patient.appointments.store'), [
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'requested_date' => '2026-11-06',
            'requested_time' => '12:15',
        ])
        ->assertRedirect(route('patient.appointments.create'))
        ->assertSessionHasErrors('requested_time');

    expect(Appointment::query()->count())->toBe(0);
});

it('refuses a legacy non-grid pending confirmation and leaves it pending', function () {
    $appointment = asfPending(asfPatient(), [
        'requested_time' => '14:37',
    ]);

    expect(fn () => asfService()->confirm($appointment, asfAdmin(), '2026-11-06', '14:37'))
        ->toThrow(DomainException::class);

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_PENDING)
        ->and(AppointmentSlotHold::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 2. Occupancy enforcement
|--------------------------------------------------------------------------
*/

it('refuses a second confirmation into the same slot and rolls back', function () {
    $first = asfPending(asfPatient('First'));
    $second = asfPending(asfPatient('Second'), [
        'requested_date' => '2026-11-05',
        'requested_time' => '10:30',
    ]);

    asfService()->confirm($first, asfAdmin(), '2026-11-05', '10:00');

    expect(fn () => asfService()->confirm($second, asfAdmin(), '2026-11-05', '10:00'))
        ->toThrow(DomainException::class);

    $second->refresh();
    expect($second->status)->toBe(Appointment::STATUS_PENDING)
        ->and($second->confirmed_date)->toBeNull()
        ->and(asfHoldExists('2026-11-05', '10:00'))->toBeTrue()
        ->and(AppointmentSlotHold::query()->where('appointment_id', $second->id)->exists())->toBeFalse();
});

it('refuses a walk-in into an occupied slot', function () {
    $occupant = asfConfirmed(asfPatient('Occupant'), [
        'confirmed_time' => '11:00',
        'requested_time' => '11:00',
    ]);
    asfAvailability()->reserveHold($occupant, '2026-11-05', '11:00');

    asfService()->createWalkIn(asfAdmin(), asfPatient('Walk')->id, Appointment::TYPE_CHECKUP, '2026-11-05', '11:00');
})->throws(DomainException::class);

it('refuses an online checkup against an already-confirmed slot but allows shared pending slots', function () {
    $patientA = asfPatient('Alpha');
    $patientB = asfPatient('Beta');
    [$userC, $patientC] = asfPortalPair();

    // Counseling stays pending and pendings never occupy: two pendings may
    // target the same slot.
    asfService()->createOnlineRequest($patientA, Appointment::TYPE_COUNSELING, '2026-11-07', '09:00');
    asfService()->createOnlineRequest($patientB, Appointment::TYPE_COUNSELING, '2026-11-07', '09:00');

    expect(Appointment::query()->where('status', Appointment::STATUS_PENDING)->count())->toBe(2);

    // Confirm one - the slot becomes occupied for NEW writes.
    $first = Appointment::query()
        ->where('patient_id', $patientA->id)
        ->where('status', Appointment::STATUS_PENDING)
        ->firstOrFail();
    asfService()->confirm($first, asfAdmin(), '2026-11-07', '09:00');

    expect(fn () => asfService()->createOnlineRequest($patientC, Appointment::TYPE_CHECKUP, '2026-11-07', '09:00', $userC))
        ->toThrow(DomainException::class);
});

it('treats a legacy 14:37 confirmation as occupying the 14:30 slot window', function () {
    asfConfirmed(asfPatient('Legacy'), [
        'confirmed_time' => '14:37',
        'requested_time' => '14:37',
    ]);

    expect(asfAvailability()->isSlotOccupied('2026-11-05', '14:30'))->toBeTrue();

    $pending = asfPending(asfPatient('Newcomer'), [
        'requested_time' => '15:00',
    ]);

    expect(fn () => asfService()->confirm($pending, asfAdmin(), '2026-11-05', '14:30'))
        ->toThrow(DomainException::class);
});

/*
|--------------------------------------------------------------------------
| 3. Closure enforcement across all five write paths
|--------------------------------------------------------------------------
*/

it('refuses confirmation into a full-day closure', function () {
    $admin = asfAdmin();
    $appointment = asfPending(asfPatient());

    asfAvailability()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => true,
        'reason' => 'Holiday.',
    ], $admin);

    expect(fn () => asfService()->confirm($appointment, $admin, '2026-11-05', '10:00'))
        ->toThrow(DomainException::class);

    $appointment->refresh();
    expect($appointment->status)->toBe(Appointment::STATUS_PENDING);
});

it('refuses walk-in creation on a fully closed date', function () {
    $admin = asfAdmin();

    asfAvailability()->createClosure([
        'start_date' => '2026-11-08',
        'end_date' => '2026-11-08',
        'all_day' => true,
        'reason' => 'Clinic day off.',
    ], $admin);

    expect(fn () => asfService()->createWalkIn($admin, asfPatient('Walk')->id, Appointment::TYPE_CHECKUP, '2026-11-08', '09:00'))
        ->toThrow(DomainException::class);
});

it('refuses a patient online request on a fully closed date through the FormRequest path', function () {
    [$user] = asfPortalPair();

    asfAvailability()->createClosure([
        'start_date' => '2026-11-09',
        'end_date' => '2026-11-09',
        'all_day' => true,
        'reason' => 'Staff training.',
    ], asfAdmin());

    $this->actingAs($user)
        ->from(route('patient.appointments.create'))
        ->post(route('patient.appointments.store'), [
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'requested_date' => '2026-11-09',
            'requested_time' => '10:00',
        ])
        ->assertRedirect(route('patient.appointments.create'))
        ->assertSessionHasErrors();

    expect(Appointment::query()->count())->toBe(0);
});

it('blocks a partial-window slot for checkup while leaving slots outside the window bookable', function () {
    $admin = asfAdmin();

    asfAvailability()->createClosure([
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-10',
        'all_day' => false,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'reason' => 'Lunch coverage.',
    ], $admin);

    $inside = asfPending(asfPatient('Inside'), ['requested_time' => '10:30']);
    expect(fn () => asfService()->confirm($inside, $admin, '2026-11-10', '10:30'))
        ->toThrow(DomainException::class);

    $outside = asfPending(asfPatient('Outside'), ['requested_time' => '09:00']);
    $confirmed = asfService()->confirm($outside, $admin, '2026-11-10', '09:00');
    expect($confirmed->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('refuses delivery-request dates under a full-day closure but never for partial windows', function () {
    $admin = asfAdmin();

    asfAvailability()->createClosure([
        'start_date' => '2026-11-11',
        'end_date' => '2026-11-11',
        'all_day' => true,
        'reason' => 'Typhoon.',
    ], $admin);

    expect(fn () => asfService()->confirm(
        asfPending(asfPatient('Delivery'), [
            'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
            'requested_time' => null,
        ]),
        $admin,
        '2026-11-11',
        null
    ))->toThrow(DomainException::class);

    asfAvailability()->createClosure([
        'start_date' => '2026-11-12',
        'end_date' => '2026-11-12',
        'all_day' => false,
        'start_time' => '10:00',
        'end_time' => '12:00',
        'reason' => 'Window only.',
    ], $admin);

    $delivery = asfService()->confirm(
        asfPending(asfPatient('Delivery2'), [
            'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
            'requested_time' => null,
        ]),
        $admin,
        '2026-11-12',
        null
    );

    expect($delivery->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AppointmentSlotHold::query()->where('appointment_id', $delivery->id)->exists())->toBeFalse();
});

it('refuses confirmation when a closure is added after the pending request was submitted', function () {
    $admin = asfAdmin();
    $appointment = asfPending(asfPatient(), [
        'requested_date' => '2026-11-13',
        'requested_time' => '09:30',
    ]);

    // Closure lands BETWEEN submission and confirmation.
    asfAvailability()->createClosure([
        'start_date' => '2026-11-13',
        'end_date' => '2026-11-13',
        'all_day' => true,
        'reason' => 'Emergency closure.',
    ], $admin);

    expect(fn () => asfService()->confirm($appointment, $admin, '2026-11-13', '09:30'))
        ->toThrow(DomainException::class);
});

/*
|--------------------------------------------------------------------------
| 4. Hold lifecycle: reserve / release / retain / move
|--------------------------------------------------------------------------
*/

it('releases the hold on cancellation so the slot becomes bookable again', function () {
    $appointment = asfPending(asfPatient());
    asfService()->confirm($appointment, asfAdmin(), '2026-11-05', '10:00');
    expect(asfHoldExists('2026-11-05', '10:00'))->toBeTrue();

    asfService()->cancel($appointment, asfAdmin(), 'Patient can no longer come.');

    expect(asfHoldExists('2026-11-05', '10:00'))->toBeFalse()
        ->and(asfAvailability()->isSlotOccupied('2026-11-05', '10:00'))->toBeFalse();

    // The freed slot can be confirmed again.
    $second = asfPending(asfPatient('Replacement'), ['requested_time' => '10:00']);
    $confirmed = asfService()->confirm($second, asfAdmin(), '2026-11-05', '10:00');
    expect($confirmed->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('handles decline on a pending row (no hold) without error', function () {
    $appointment = asfPending(asfPatient());

    $declined = asfService()->decline($appointment, asfAdmin(), 'Outside service scope.');

    expect($declined->status)->toBe(Appointment::STATUS_DECLINED)
        ->and(AppointmentSlotHold::query()->count())->toBe(0);
});

it('retains the hold after completion (completed keeps occupancy)', function () {
    $appointment = asfPending(asfPatient());
    asfService()->confirm($appointment, asfAdmin(), '2026-11-05', '10:00');

    // Completion requires the schedule to be due: freeze past the slot.
    Carbon::setTestNow(Carbon::parse('2026-11-05 11:00', 'Asia/Manila'));
    asfService()->complete($appointment, asfAdmin());

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED)
        ->and(asfHoldExists('2026-11-05', '10:00'))->toBeTrue()
        ->and(asfAvailability()->isSlotOccupied('2026-11-05', '10:00'))->toBeTrue();
});

it('releases the hold through a patient-approved cancellation path', function () {
    [$user, $patient] = asfPortalPair();
    $appointment = asfPending(asfPatient('Own'), ['requested_time' => '13:00']);
    // Route the confirmed appointment to the portal patient.
    $appointment->patient_id = $patient->id;
    $appointment->save();

    asfService()->confirm($appointment, asfAdmin(), '2026-11-05', '13:00');
    expect(asfHoldExists('2026-11-05', '13:00'))->toBeTrue();

    $changeRequest = asfService()->requestAppointmentCancellation($appointment, $patient, $user, 'Schedule conflict, please cancel.');
    asfService()->approveCancellationRequest($changeRequest, asfAdmin(), null);

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and(asfHoldExists('2026-11-05', '13:00'))->toBeFalse();
});

it('moves the hold on an approved reschedule and frees the old slot', function () {
    [$user, $patient] = asfPortalPair();
    $appointment = asfConfirmed(asfPatient('Mover'), ['requested_time' => '09:00', 'confirmed_time' => '09:00']);
    $appointment->patient_id = $patient->id;
    $appointment->save();

    asfAvailability()->reserveHold($appointment, '2026-11-05', '09:00');

    $changeRequest = asfService()->requestAppointmentReschedule($appointment, $patient, $user, '2026-11-06', '14:00', 'Preferred afternoon slot.');
    asfService()->approveRescheduleRequest($changeRequest, asfAdmin(), null);

    $appointment->refresh();
    expect($appointment->confirmed_date?->toDateString())->toBe('2026-11-06')
        ->and((string) $appointment->confirmed_time)->toContain('14:00')
        ->and(asfHoldExists('2026-11-05', '09:00'))->toBeFalse()
        ->and(asfHoldExists('2026-11-06', '14:00'))->toBeTrue();
});

it('creates a fresh hold when a legacy confirmed appointment without one is rescheduled', function () {
    [$user, $patient] = asfPortalPair();
    // forceCreate makes no hold: the pre-Phase-1 legacy shape.
    $appointment = asfConfirmed(asfPatient('LegacyNoHold'), [
        'requested_time' => '15:00',
        'confirmed_time' => '15:00',
    ]);
    $appointment->patient_id = $patient->id;
    $appointment->save();

    expect(AppointmentSlotHold::query()->where('appointment_id', $appointment->id)->exists())->toBeFalse();

    $changeRequest = asfService()->requestAppointmentReschedule($appointment, $patient, $user, '2026-11-06', '11:30', 'Moving to morning.');
    asfService()->approveRescheduleRequest($changeRequest, asfAdmin(), null);

    expect(asfHoldExists('2026-11-06', '11:30'))->toBeTrue()
        ->and($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('refuses a reschedule approval whose proposal is non-grid and leaves everything unchanged', function () {
    [$user, $patient] = asfPortalPair();
    $appointment = asfConfirmed(asfPatient('NonGridProposal'), [
        'requested_time' => '10:00',
        'confirmed_time' => '10:00',
    ]);
    $appointment->patient_id = $patient->id;
    $appointment->save();

    // Direct fixture bypasses the patient-path validation - the
    // authority boundary must still refuse the approval.
    $changeRequest = AppointmentChangeRequest::forceCreate([
        'appointment_id' => $appointment->id,
        'type' => AppointmentChangeRequest::TYPE_RESCHEDULE,
        'proposed_date' => '2026-11-06',
        'proposed_time' => '10:47',
        'status' => AppointmentChangeRequest::STATUS_PENDING,
    ]);

    expect(fn () => asfService()->approveRescheduleRequest($changeRequest, asfAdmin(), null))
        ->toThrow(DomainException::class);

    $appointment->refresh();
    $changeRequest->refresh();
    expect($appointment->confirmed_date?->toDateString())->toBe('2026-11-05')
        ->and((string) $appointment->confirmed_time)->toContain('10:00')
        ->and($changeRequest->status)->toBe(AppointmentChangeRequest::STATUS_PENDING);
});

it('refuses a reschedule proposal into an occupied slot but allows the appointment own slot', function () {
    [$user, $patient] = asfPortalPair();
    asfConfirmed(asfPatient('Blocker'), ['confirmed_time' => '09:00', 'requested_time' => '09:00']);
    $occupant = Appointment::query()->where('confirmed_time', '09:00')->firstOrFail();
    asfAvailability()->reserveHold($occupant, '2026-11-05', '09:00');

    $appointment = asfConfirmed(asfPatient('Proposer'), [
        'requested_time' => '10:00',
        'confirmed_time' => '10:00',
    ]);
    $appointment->patient_id = $patient->id;
    $appointment->save();

    // Own current slot: never a blocker (moving away from it).
    expect(fn () => asfService()->requestAppointmentReschedule($appointment, $patient, $user, '2026-11-05', '10:00', 'Keep, but this is identical.'))
        ->toThrow(DomainException::class); // identical-to-current guard, not occupancy

    // Someone else's held slot: refused.
    expect(fn () => asfService()->requestAppointmentReschedule($appointment, $patient, $user, '2026-11-05', '09:00', 'Take the held slot.'))
        ->toThrow(DomainException::class);
});

/*
|--------------------------------------------------------------------------
| 5. Delivery compatibility
|--------------------------------------------------------------------------
*/

it('confirms a delivery request with a date-only time and never reserves a hold', function () {
    $appointment = asfPending(asfPatient('Birth'), [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
    ]);

    $confirmed = asfService()->confirm($appointment, asfAdmin(), '2026-11-14', null);

    expect($confirmed->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AppointmentSlotHold::query()->count())->toBe(0)
        ->and(asfAvailability()->isSlotOccupied('2026-11-14', '09:00'))->toBeFalse();
});

it('creates a walk-in delivery request date-only without a hold', function () {
    $walkIn = asfService()->createWalkIn(
        asfAdmin(),
        asfPatient('BirthWalk')->id,
        Appointment::TYPE_DELIVERY_REQUEST,
        '2026-11-15',
        null
    );

    expect($walkIn->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($walkIn->confirmed_time)->toBeNull()
        ->and(AppointmentSlotHold::query()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| 6. Staff calendar HTTP confirmation surface (DomainException path)
|--------------------------------------------------------------------------
*/

it('surfaces an occupied-slot refusal as a session error on the staff confirm route', function () {
    $staff = asfStaff();
    $occupant = asfConfirmed(asfPatient('HttpBlocker'), ['confirmed_time' => '15:30', 'requested_time' => '15:30']);
    asfAvailability()->reserveHold($occupant, '2026-11-05', '15:30');

    $pending = asfPending(asfPatient('HttpTarget'), ['requested_time' => '15:30']);

    $this->actingAs($staff)
        ->from(route('appointments.index'))
        ->patch(route('appointments.confirm', $pending), [
            'confirmed_date' => '2026-11-05',
            'confirmed_time' => '15:30',
        ])
        ->assertRedirect(route('appointments.index'))
        ->assertSessionHas('errorAppointmentId', $pending->id)
        ->assertSessionHasErrors('appointment');

    $pending->refresh();
    expect($pending->status)->toBe(Appointment::STATUS_PENDING);
});

it('rejects a staff confirmation into a closure with a session error', function () {
    $admin = asfAdmin();
    asfAvailability()->createClosure([
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-16',
        'all_day' => true,
        'reason' => 'Clinic closed.',
    ], $admin);

    $pending = asfPending(asfPatient('ClosedTarget'), [
        'requested_date' => '2026-11-16',
        'requested_time' => '09:00',
    ]);

    $this->actingAs($admin)
        ->from(route('appointments.index'))
        ->patch(route('appointments.confirm', $pending), [
            'confirmed_date' => '2026-11-16',
            'confirmed_time' => '09:00',
        ])
        ->assertRedirect(route('appointments.index'))
        ->assertSessionHasErrors('appointment');

    expect($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING);
});
