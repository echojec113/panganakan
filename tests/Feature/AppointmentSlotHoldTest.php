<?php

use App\Models\Appointment;
use App\Models\AppointmentSlotHold;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicAvailabilityService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 1 - Slot-hold domain operations + occupancy semantics
|--------------------------------------------------------------------------
| SQLite :memory: only. Covers the unique constraints (the concurrency
| barrier), reserve/move/release/rollback, legacy 14:37 occupancy,
| confirmed/completed vs cancelled/declined vs pending occupancy, and
| delivery-request exclusion from holds and occupancy.
*/

function ashService(): ClinicAvailabilityService
{
    return app(ClinicAvailabilityService::class);
}

function ashAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Hold Admin']);
}

function ashPatient(string $first = 'Hana'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => 'Reyes',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => strtolower($first) . '@ashold.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function ashAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '09:00',
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '09:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Schema constraints (unique barrier)
|--------------------------------------------------------------------------
*/

it('refuses a second hold for the same date and slot start', function () {
    $first = ashAppointment(ashPatient('First'));
    $second = ashAppointment(ashPatient('Second'), ['requested_time' => '09:30', 'confirmed_time' => '09:30']);

    AppointmentSlotHold::forceCreate([
        'hold_date' => '2026-11-05',
        'slot_start' => '09:00',
        'appointment_id' => $first->id,
    ]);

    expect(fn () => AppointmentSlotHold::forceCreate([
        'hold_date' => '2026-11-05',
        'slot_start' => '09:00',
        'appointment_id' => $second->id,
    ]))->toThrow(QueryException::class);
});

it('refuses a second hold row for the same appointment', function () {
    $appointment = ashAppointment(ashPatient('Solo'));

    AppointmentSlotHold::forceCreate([
        'hold_date' => '2026-11-05',
        'slot_start' => '09:00',
        'appointment_id' => $appointment->id,
    ]);

    expect(fn () => AppointmentSlotHold::forceCreate([
        'hold_date' => '2026-11-06',
        'slot_start' => '10:00',
        'appointment_id' => $appointment->id,
    ]))->toThrow(QueryException::class);
});

/*
|--------------------------------------------------------------------------
| Reserve / move / release / rollback
|--------------------------------------------------------------------------
*/

it('reserves a hold for a confirmed appointment and marks the slot occupied', function () {
    $service = ashService();
    $appointment = ashAppointment(ashPatient('Reserve'));

    $hold = $service->reserveHold($appointment, '2026-11-05', '09:00');

    expect($hold->appointment_id)->toBe($appointment->id)
        ->and($service->hasHold($appointment))->toBeTrue()
        ->and($service->isSlotOccupied('2026-11-05', '09:00'))->toBeTrue()
        ->and($service->isSlotOccupied('2026-11-05', '09:30'))->toBeFalse();
});

it('refuses a second reservation of an occupied slot with a clean domain error', function () {
    $service = ashService();

    $service->reserveHold(ashAppointment(ashPatient('Winner')), '2026-11-05', '09:00');

    expect(fn () => $service->reserveHold(ashAppointment(ashPatient('Loser')), '2026-11-05', '09:00'))
        ->toThrow(DomainException::class);

    expect(AppointmentSlotHold::count())->toBe(1);
});

it('moves an existing hold in place without creating a second row', function () {
    $service = ashService();
    // confirmed_time null: occupancy comes ONLY from the hold row, so the
    // move is observable without the legacy-window query interfering.
    $appointment = ashAppointment(ashPatient('Mover'), ['confirmed_time' => null]);

    $service->reserveHold($appointment, '2026-11-05', '09:00');
    $moved = $service->moveHold($appointment, '2026-11-06', '14:00');

    expect(AppointmentSlotHold::count())->toBe(1)
        ->and($moved->hold_date)->toBe('2026-11-06')
        ->and($moved->slot_start)->toBe('14:00')
        ->and($service->isSlotOccupied('2026-11-05', '09:00'))->toBeFalse()
        ->and($service->isSlotOccupied('2026-11-06', '14:00'))->toBeTrue();
});

it('refuses to move a hold onto a slot another hold already owns', function () {
    $service = ashService();

    $first = ashAppointment(ashPatient('Owns'));
    $second = ashAppointment(ashPatient('Takes'), [
        'confirmed_date' => '2026-11-06',
        'confirmed_time' => '14:00',
    ]);

    $service->reserveHold($first, '2026-11-05', '09:00');
    $service->reserveHold($second, '2026-11-06', '14:00');

    expect(fn () => $service->moveHold($first, '2026-11-06', '14:00'))
        ->toThrow(DomainException::class);

    $first->refresh();
    expect($service->holdFor($first)->slot_start)->toBe('09:00');
});

it('refuses to move a hold that does not exist', function () {
    $appointment = ashAppointment(ashPatient('NoHold'));

    expect(fn () => ashService()->moveHold($appointment, '2026-11-06', '10:00'))
        ->toThrow(DomainException::class);
});

it('releases a hold on cancellation/decline and is idempotent when none exists', function () {
    $service = ashService();
    $appointment = ashAppointment(ashPatient('Release'), ['confirmed_time' => null]);

    $service->reserveHold($appointment, '2026-11-05', '09:00');

    expect($service->releaseHold($appointment))->toBeTrue()
        ->and($service->hasHold($appointment))->toBeFalse()
        ->and($service->releaseHold($appointment))->toBeFalse()
        ->and($service->isSlotOccupied('2026-11-05', '09:00'))->toBeFalse();
});

it('rolls back the hold insert when the surrounding transaction fails', function () {
    $service = ashService();
    $appointment = ashAppointment(ashPatient('Rollback'), ['confirmed_time' => null]);

    try {
        DB::transaction(function () use ($service, $appointment) {
            $service->reserveHold($appointment, '2026-11-05', '09:00');

            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(AppointmentSlotHold::count())->toBe(0)
        ->and($service->hasHold($appointment))->toBeFalse()
        ->and($service->isSlotOccupied('2026-11-05', '09:00'))->toBeFalse();
});

it('refuses holds for pending appointments and delivery requests', function () {
    $service = ashService();

    $pending = ashAppointment(ashPatient('Pending'), [
        'confirmed_date' => null,
        'confirmed_time' => null,
        'status' => Appointment::STATUS_PENDING,
    ]);

    expect(fn () => $service->reserveHold($pending, '2026-11-05', '09:00'))
        ->toThrow(DomainException::class);

    $delivery = ashAppointment(ashPatient('Delivery'), [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
        'confirmed_time' => null,
    ]);

    expect(fn () => $service->reserveHold($delivery, '2026-11-05', '09:00'))
        ->toThrow(DomainException::class);

    expect(AppointmentSlotHold::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Occupancy semantics: legacy window, statuses, pending, delivery
|--------------------------------------------------------------------------
*/

it('lets a legacy non-grid appointment at 14:37 occupy the 14:30 slot', function () {
    $service = ashService();

    ashAppointment(ashPatient('Legacy'), ['confirmed_time' => '14:37']);

    expect($service->isSlotOccupied('2026-11-05', '14:30'))->toBeTrue()
        ->and($service->slotOccupyingAppointments('2026-11-05', '14:30'))->toHaveCount(1)
        ->and($service->isSlotOccupied('2026-11-05', '14:00'))->toBeFalse()
        ->and($service->isSlotOccupied('2026-11-05', '15:00'))->toBeFalse();

    // The legacy appointment is never rewritten and never given a hold.
    expect(Appointment::where('confirmed_time', '14:37')->count())->toBe(1)
        ->and(AppointmentSlotHold::count())->toBe(0);
});

it('treats confirmed and completed as occupying while cancelled, declined and pending do not', function () {
    $service = ashService();

    ashAppointment(ashPatient('ConfirmedOne'), ['confirmed_time' => '09:00']);
    ashAppointment(ashPatient('CompletedOne'), ['confirmed_time' => '10:00', 'status' => Appointment::STATUS_COMPLETED]);
    ashAppointment(ashPatient('CancelledOne'), ['confirmed_time' => '11:00', 'status' => Appointment::STATUS_CANCELLED]);
    ashAppointment(ashPatient('DeclinedOne'), ['confirmed_time' => '13:00', 'status' => Appointment::STATUS_DECLINED]);

    // Pending online request: requested time only, no confirmed schedule.
    Appointment::forceCreate([
        'patient_id' => ashPatient('PendingOne')->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '15:00',
        'status' => Appointment::STATUS_PENDING,
    ]);

    expect($service->isSlotOccupied('2026-11-05', '09:00'))->toBeTrue()
        ->and($service->isSlotOccupied('2026-11-05', '10:00'))->toBeTrue()
        ->and($service->isSlotOccupied('2026-11-05', '11:00'))->toBeFalse()
        ->and($service->isSlotOccupied('2026-11-05', '13:00'))->toBeFalse()
        ->and($service->isSlotOccupied('2026-11-05', '15:00'))->toBeFalse();
});

it('allows multiple pending requests for the same slot without occupying it', function () {
    $service = ashService();

    foreach (['PatientA', 'PatientB', 'PatientC'] as $name) {
        Appointment::forceCreate([
            'patient_id' => ashPatient($name)->id,
            'appointment_type' => Appointment::TYPE_CHECKUP,
            'source' => Appointment::SOURCE_ONLINE,
            'requested_date' => '2026-11-05',
            'requested_time' => '09:00',
            'status' => Appointment::STATUS_PENDING,
        ]);
    }

    expect(Appointment::where('status', Appointment::STATUS_PENDING)->count())->toBe(3)
        ->and($service->isSlotOccupied('2026-11-05', '09:00'))->toBeFalse()
        ->and($service->slotState('2026-11-05', '09:00', now: \Carbon\Carbon::parse('2026-11-01 08:00:00', 'Asia/Manila')))
        ->toBe(ClinicAvailabilityService::STATE_AVAILABLE);
});

it('never counts a delivery request as slot occupancy, even with a recorded time', function () {
    $service = ashService();

    Appointment::forceCreate([
        'patient_id' => ashPatient('DeliveryTimed')->id,
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    expect($service->isSlotOccupied('2026-11-05', '10:00'))->toBeFalse()
        ->and($service->slotOccupyingAppointments('2026-11-05', '10:00'))->toHaveCount(0);
});

it('keeps completed occupancy via the hold retained after completion', function () {
    $service = ashService();

    $appointment = ashAppointment(ashPatient('CompletedHold'), [
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    $service->reserveHold($appointment, '2026-11-05', '09:00');

    // Completion retains the hold: the service offers no release path for
    // completed appointments - occupancy survives.
    expect($service->hasHold($appointment))->toBeTrue()
        ->and($service->isSlotOccupied('2026-11-05', '09:00'))->toBeTrue();
});
