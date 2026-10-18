<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\ClinicUnavailability;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicAvailabilityService;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 1 - Closure domain operations (create/update/remove + audit +
| affected-appointment preview + explicit-confirmation gate)
|--------------------------------------------------------------------------
| SQLite :memory: only. Existing appointments are NEVER modified by any
| closure operation - tests assert that below.
*/

function ccmService(): ClinicAvailabilityService
{
    return app(ClinicAvailabilityService::class);
}

function ccmAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'CCM Admin']);
}

function ccmPatient(string $first = 'Claire'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => 'Cruz',
        'age' => 26,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => strtolower($first) . '@ccm.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function ccmAppointment(Patient $patient, array $overrides = []): Appointment
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
| Create / update / remove + audit
|--------------------------------------------------------------------------
*/

it('creates a closure with provenance and an in-transaction audit row', function () {
    $admin = ccmAdmin();

    $closure = ccmService()->createClosure([
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => true,
        'reason' => 'Annual staff training',
    ], $admin);

    expect($closure->created_by)->toBe($admin->id)
        ->and($closure->reason)->toBe('Annual staff training')
        ->and($closure->start_date->toDateString())->toBe('2026-11-20')
        ->and($closure->end_date->toDateString())->toBe('2026-11-20');

    $audit = AuditLog::where('action', 'CLINIC_UNAVAILABILITY_CREATED')
        ->where('module', 'CLINIC_UNAVAILABILITY')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->patient_id)->toBeNull()
        ->and($audit->description)->toContain('#' . $closure->id);
});

it('updates a closure and audits the change', function () {
    $admin = ccmAdmin();
    $service = ccmService();

    $closure = $service->createClosure([
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => true,
    ], $admin);

    $updated = $service->updateClosure($closure, [
        'start_date' => '2026-11-21',
        'end_date' => '2026-11-21',
    ], $admin);

    expect($updated->start_date->toDateString())->toBe('2026-11-21')
        ->and($service->isDateFullyClosed('2026-11-20'))->toBeFalse()
        ->and($service->isDateFullyClosed('2026-11-21'))->toBeTrue();

    expect(AuditLog::where('action', 'CLINIC_UNAVAILABILITY_UPDATED')
        ->where('module', 'CLINIC_UNAVAILABILITY')->exists())->toBeTrue();
});

it('removes a closure, reopens the date and audits the removal', function () {
    $admin = ccmAdmin();
    $service = ccmService();

    $closure = $service->createClosure([
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => true,
    ], $admin);

    $service->removeClosure($closure, $admin);

    expect(ClinicUnavailability::find($closure->id))->toBeNull()
        ->and($service->isDateFullyClosed('2026-11-20'))->toBeFalse()
        ->and(AuditLog::where('action', 'CLINIC_UNAVAILABILITY_REMOVED')
            ->where('module', 'CLINIC_UNAVAILABILITY')->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Affected-appointment preview + explicit-confirmation gate
|--------------------------------------------------------------------------
*/

it('counts affected confirmed and pending appointments separately', function () {
    $service = ccmService();

    ccmAppointment(ccmPatient('Confirmed'), [
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => '09:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    // Pending: requested schedule only - effective via COALESCE.
    Appointment::forceCreate([
        'patient_id' => ccmPatient('Pending')->id,
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-20',
        'requested_time' => '10:00',
        'status' => Appointment::STATUS_PENDING,
    ]);

    // Outside the date range: not affected.
    ccmAppointment(ccmPatient('Outside'), [
        'confirmed_date' => '2026-11-25',
        'requested_date' => '2026-11-25',
        'confirmed_time' => '09:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $affected = $service->affectedAppointments([
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => true,
    ]);

    expect($affected['confirmed_count'])->toBe(1)
        ->and($affected['pending_count'])->toBe(1);
});

it('requires explicit confirmation when a closure touches confirmed appointments', function () {
    $service = ccmService();

    ccmAppointment(ccmPatient('Kept'), [
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => '09:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $data = [
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => true,
    ];

    expect(fn () => $service->createClosure($data, ccmAdmin()))
        ->toThrow(DomainException::class);

    expect(ClinicUnavailability::count())->toBe(0);

    $closure = $service->createClosure($data, ccmAdmin(), confirmAffected: true);

    expect($closure)->not->toBeNull();
});

it('never modifies, cancels or reschedules existing appointments when saving a closure', function () {
    $service = ccmService();

    $appointment = ccmAppointment(ccmPatient('Untouched'), [
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => '09:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ]);
    $snapshot = DB::table('appointments')->where('id', $appointment->id)->first();

    $service->createClosure([
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => true,
    ], ccmAdmin(), confirmAffected: true);

    $after = DB::table('appointments')->where('id', $appointment->id)->first();

    expect((array) $after)->toBe((array) $snapshot);
});

it('scopes a partial-window closure preview to effective times inside the window', function () {
    $service = ccmService();

    // Inside 10:00-11:00 window.
    ccmAppointment(ccmPatient('Inside'), [
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => '10:30',
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    // Same date, outside the window.
    ccmAppointment(ccmPatient('OutsideWindow'), [
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => '14:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    // Date-only delivery request on the same date: no effective time - not
    // matched by a time-window closure.
    Appointment::forceCreate([
        'patient_id' => ccmPatient('Delivery')->id,
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-20',
        'requested_time' => null,
        'confirmed_date' => '2026-11-20',
        'confirmed_time' => null,
        'status' => Appointment::STATUS_CONFIRMED,
    ]);

    $affected = $service->affectedAppointments([
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => false,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    expect($affected['confirmed_count'])->toBe(1)
        ->and($affected['confirmed']->first()->patient->first_name)->toBe('Inside');
});
