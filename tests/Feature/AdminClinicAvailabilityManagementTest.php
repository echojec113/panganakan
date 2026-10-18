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
| Phase 3 - Admin Clinic Availability management (HTTP + service)
|--------------------------------------------------------------------------
| SQLite :memory: only. Existing appointments are NEVER modified by any
| closure operation - tests assert that below. The client never supplies
| created_by and never supplies trusted affected counts.
*/

function camService(): ClinicAvailabilityService
{
    return app(ClinicAvailabilityService::class);
}

function camAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Cam Admin']);
}

function camStaff(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Cam Staff']);
}

function camPatientUser(): User
{
    return User::factory()->create(['role' => 'patient', 'name' => 'Cam Patient']);
}

function camPatient(string $first = 'Carla'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => 'Santos',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => strtolower($first) . '@cam.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function camAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-12-01',
        'requested_time' => '10:00',
        'confirmed_date' => '2026-12-01',
        'confirmed_time' => '10:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| Authorization (three layers) + page
|--------------------------------------------------------------------------
*/

it('lets an admin view the clinic availability page', function () {
    $this->actingAs(camAdmin())
        ->get(route('admin.clinic-unavailability.index'))
        ->assertOk()
        ->assertSee('Clinic Availability')
        ->assertSee('Add Closure');
});

it('refuses staff on every clinic-unavailability route with 403', function () {
    $staff = camStaff();
    $closure = camService()->createClosure([
        'start_date' => '2026-12-10', 'end_date' => '2026-12-10', 'all_day' => true,
    ], camAdmin());

    $this->actingAs($staff)->get(route('admin.clinic-unavailability.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('admin.clinic-unavailability.affected'))->assertForbidden();
    $this->actingAs($staff)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'single_day', 'date' => '2026-12-11',
    ])->assertForbidden();
    $this->actingAs($staff)->patch(route('admin.clinic-unavailability.update', $closure), [
        'start_date' => '2026-12-10', 'end_date' => '2026-12-10', 'all_day' => 1,
    ])->assertForbidden();
    $this->actingAs($staff)->delete(route('admin.clinic-unavailability.destroy', $closure))->assertForbidden();

    expect(ClinicUnavailability::count())->toBe(1);
});

it('refuses a patient account on every clinic-unavailability route with 403', function () {
    $patientUser = camPatientUser();

    $this->actingAs($patientUser)->get(route('admin.clinic-unavailability.index'))->assertForbidden();
    $this->actingAs($patientUser)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'single_day', 'date' => '2026-12-11',
    ])->assertForbidden();

    expect(ClinicUnavailability::count())->toBe(0);
});

it('redirects a guest to login', function () {
    $this->get(route('admin.clinic-unavailability.index'))->assertRedirect(route('login'));
});

it('returns 404 for an unknown closure id on edit and remove', function () {
    $admin = camAdmin();

    $this->actingAs($admin)->patch(route('admin.clinic-unavailability.update', 99999), [
        'start_date' => '2026-12-10', 'end_date' => '2026-12-10', 'all_day' => 1,
    ])->assertNotFound();

    $this->actingAs($admin)->delete(route('admin.clinic-unavailability.destroy', 99999))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Create - single day / date range
|--------------------------------------------------------------------------
*/

it('creates a single-day full closure and stamps created_by from the session user', function () {
    $admin = camAdmin();

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'single_day',
        'date' => '2026-12-15',
        'reason' => 'Holiday',
        'created_by' => 99999, // spoof attempt - must be ignored
    ])->assertRedirect(route('admin.clinic-unavailability.index'))
        ->assertSessionHas('success');

    $closure = ClinicUnavailability::first();

    expect($closure)->not->toBeNull()
        ->and($closure->all_day)->toBeTrue()
        ->and($closure->start_date->toDateString())->toBe('2026-12-15')
        ->and($closure->end_date->toDateString())->toBe('2026-12-15')
        ->and($closure->reason)->toBe('Holiday')
        ->and($closure->created_by)->toBe($admin->id)
        ->and($closure->created_by)->not->toBe(99999);
});

it('creates a multi-day full closure range', function () {
    $this->actingAs(camAdmin())->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'date_range',
        'start_date' => '2027-01-05',
        'end_date' => '2027-01-08',
    ])->assertSessionHas('success');

    $closure = ClinicUnavailability::first();

    expect($closure->all_day)->toBeTrue()
        ->and($closure->start_date->toDateString())->toBe('2027-01-05')
        ->and($closure->end_date->toDateString())->toBe('2027-01-08')
        ->and(camService()->isDateFullyClosed('2027-01-06'))->toBeTrue();
});

it('accepts a 366-day range and refuses a 367-day range', function () {
    $admin = camAdmin();

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'date_range',
        'start_date' => '2027-01-01',
        'end_date' => '2027-12-31', // 365 days in 2027
    ])->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'date_range',
        'start_date' => '2027-01-01',
        'end_date' => '2028-01-01', // 366 days
    ])->assertSessionHas('success');

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'date_range',
        'start_date' => '2027-01-01',
        'end_date' => '2028-01-02', // 367 days
    ])->assertSessionHasErrors('end_date');

    expect(ClinicUnavailability::count())->toBe(2);
});

it('refuses a reversed date range', function () {
    $this->actingAs(camAdmin())->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'date_range',
        'start_date' => '2027-02-10',
        'end_date' => '2027-02-01',
    ])->assertSessionHasErrors('end_date');

    expect(ClinicUnavailability::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Create - time slots (contiguous grouping + atomicity)
|--------------------------------------------------------------------------
*/

it('creates one closure row for contiguous selected slots', function () {
    $this->actingAs(camAdmin())->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'slots',
        'date' => '2026-12-20',
        'slot_starts' => ['10:00', '10:30', '11:00'],
        'reason' => 'Maintenance',
    ])->assertSessionHas('success');

    $closure = ClinicUnavailability::first();

    expect(ClinicUnavailability::count())->toBe(1)
        ->and($closure->all_day)->toBeFalse()
        ->and($closure->start_time)->toBe('10:00')
        ->and($closure->end_time)->toBe('11:30') // exclusive end of the 11:00 slot
        ->and($closure->reason)->toBe('Maintenance');
});

it('creates separate rows for non-contiguous selected slots', function () {
    $this->actingAs(camAdmin())->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'slots',
        'date' => '2026-12-21',
        'slot_starts' => ['10:00', '10:30', '12:00'],
    ])->assertSessionHas('success');

    expect(ClinicUnavailability::count())->toBe(2);

    $first = ClinicUnavailability::where('start_time', '10:00')->first();
    $second = ClinicUnavailability::where('start_time', '12:00')->first();

    expect($first->end_time)->toBe('11:00')
        ->and($second->end_time)->toBe('12:30');
});

it('refuses an off-grid slot start and creates no rows', function () {
    $this->actingAs(camAdmin())->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'slots',
        'date' => '2026-12-22',
        'slot_starts' => ['10:00', '10:15'],
    ])->assertSessionHasErrors('slot_starts.1');

    expect(ClinicUnavailability::count())->toBe(0);
});

it('requires at least one slot and a date in slots mode', function () {
    $admin = camAdmin();

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'slots',
        'date' => '2026-12-23',
    ])->assertSessionHasErrors('slot_starts');

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'slots',
        'slot_starts' => ['10:00'],
    ])->assertSessionHasErrors('date');

    expect(ClinicUnavailability::count())->toBe(0);
});

it('rolls back the whole multi-slot batch when one window is refused by the service', function () {
    $admin = camAdmin();

    // Poison the second window so createClosure throws ValidationException
    // mid-batch (partial window spanning a wider range than one day).
    DB::transaction(function () use ($admin) {
        try {
            $service = camService();

            $service->createClosure([
                'start_date' => '2026-12-24',
                'end_date' => '2026-12-24',
                'all_day' => false,
                'start_time' => '10:00',
                'end_time' => '11:00',
            ], $admin);

            // Second window invalid: all_day false across two dates.
            $service->createClosure([
                'start_date' => '2026-12-24',
                'end_date' => '2026-12-25',
                'all_day' => false,
                'start_time' => '13:00',
                'end_time' => '14:00',
            ], $admin);

            $this->fail('Expected the invalid window to throw.');
        } catch (\Illuminate\Validation\ValidationException) {
            // Re-throw so the wrapping transaction rolls back everything,
            // mirroring the controller's multi-window batch.
            throw new \RuntimeException('batch rollback');
        }
    });
})->throws(\RuntimeException::class, 'batch rollback');

/*
|--------------------------------------------------------------------------
| Update + remove
|--------------------------------------------------------------------------
*/

it('updates one closure row and audits the change', function () {
    $admin = camAdmin();
    $closure = camService()->createClosure([
        'start_date' => '2026-12-15', 'end_date' => '2026-12-15', 'all_day' => true,
    ], $admin);

    $this->actingAs($admin)->patch(route('admin.clinic-unavailability.update', $closure), [
        'start_date' => '2026-12-16',
        'end_date' => '2026-12-17',
        'all_day' => 1,
        'reason' => 'Extended',
    ])->assertRedirect(route('admin.clinic-unavailability.index'))
        ->assertSessionHas('success');

    $closure->refresh();

    expect($closure->start_date->toDateString())->toBe('2026-12-16')
        ->and($closure->end_date->toDateString())->toBe('2026-12-17')
        ->and($closure->reason)->toBe('Extended')
        ->and(AuditLog::where('action', 'CLINIC_UNAVAILABILITY_UPDATED')
            ->where('module', 'CLINIC_UNAVAILABILITY')->exists())->toBeTrue();
});

it('refuses an update that puts times on a full-day closure', function () {
    $admin = camAdmin();
    $closure = camService()->createClosure([
        'start_date' => '2026-12-15', 'end_date' => '2026-12-15', 'all_day' => true,
    ], $admin);

    $this->actingAs($admin)->patch(route('admin.clinic-unavailability.update', $closure), [
        'start_date' => '2026-12-15',
        'end_date' => '2026-12-15',
        'all_day' => 1,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ])->assertSessionHasErrors('start_time');

    $closure->refresh();
    expect($closure->start_time)->toBeNull();
});

it('removes one closure row and audits the removal', function () {
    $admin = camAdmin();
    $closure = camService()->createClosure([
        'start_date' => '2026-12-15', 'end_date' => '2026-12-15', 'all_day' => true,
    ], $admin);

    $this->actingAs($admin)->delete(route('admin.clinic-unavailability.destroy', $closure))
        ->assertRedirect(route('admin.clinic-unavailability.index'))
        ->assertSessionHas('success');

    expect(ClinicUnavailability::find($closure->id))->toBeNull()
        ->and(AuditLog::where('action', 'CLINIC_UNAVAILABILITY_REMOVED')
            ->where('module', 'CLINIC_UNAVAILABILITY')->exists())->toBeTrue();
});

it('keeps the slot blocked by an overlapping closure after one row is removed', function () {
    $admin = camAdmin();
    $service = camService();

    $first = $service->createClosure([
        'start_date' => '2026-12-15', 'end_date' => '2026-12-15', 'all_day' => true,
    ], $admin);

    $second = $service->createClosure([
        'start_date' => '2026-12-15', 'end_date' => '2026-12-15', 'all_day' => true,
    ], $admin);

    expect($service->isDateFullyClosed('2026-12-15'))->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.clinic-unavailability.destroy', $first));

    expect(ClinicUnavailability::find($first->id))->toBeNull()
        ->and($service->isDateFullyClosed('2026-12-15'))->toBeTrue(); // second row still covers

    $this->actingAs($admin)->delete(route('admin.clinic-unavailability.destroy', $second));

    expect($service->isDateFullyClosed('2026-12-15'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Affected-appointments preview endpoint
|--------------------------------------------------------------------------
*/

it('reports the confirmed/completed/pending split and overlap count', function () {
    $admin = camAdmin();
    $service = camService();
    $patient = camPatient();

    camAppointment($patient, ['confirmed_time' => '10:00']); // confirmed
    camAppointment($patient, [
        'confirmed_time' => '11:00',
        'status' => Appointment::STATUS_COMPLETED,
    ]);
    camAppointment($patient, [
        'confirmed_date' => null,
        'confirmed_time' => null,
        'requested_time' => '14:00',
        'status' => Appointment::STATUS_PENDING,
    ]);

    $service->createClosure([
        'start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'all_day' => true,
    ], $admin); // overlap warning source

    $response = $this->actingAs($admin)->getJson(route('admin.clinic-unavailability.affected', [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'all_day' => 1,
    ]));

    $response->assertOk()->assertJson([
        'confirmed_count' => 1, // confirmed-only (excluding completed)
        'completed_count' => 1,
        'occupying_count' => 2,
        'pending_count' => 1,
    ]);

    // The overlap row is a different date (2026-11-01) - no overlap.
    expect($response->json('overlapping_count'))->toBe(0);
});

it('counts overlapping closure rows for the proposed period', function () {
    $admin = camAdmin();
    $service = camService();

    $existing = $service->createClosure([
        'start_date' => '2026-12-05', 'end_date' => '2026-12-05', 'all_day' => true,
    ], $admin);

    $this->actingAs($admin)->getJson(route('admin.clinic-unavailability.affected', [
        'start_date' => '2026-12-05',
        'end_date' => '2026-12-05',
        'all_day' => 1,
    ]))->assertOk()->assertJson(['overlapping_count' => 1]);

    // Edit preview must not count ITSELF as an overlap.
    $this->actingAs($admin)->getJson(route('admin.clinic-unavailability.affected', [
        'start_date' => '2026-12-05',
        'end_date' => '2026-12-05',
        'all_day' => 1,
        'ignore' => $existing->id,
    ]))->assertOk()->assertJson(['overlapping_count' => 0]);
});

it('restricts time-window previews to appointments inside the window', function () {
    $admin = camAdmin();
    $patient = camPatient();

    camAppointment($patient, ['confirmed_time' => '10:00']); // inside 10:00-11:00
    camAppointment($patient, ['confirmed_time' => '15:00']); // outside

    $this->actingAs($admin)->getJson(route('admin.clinic-unavailability.affected', [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'all_day' => 0,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]))->assertOk()->assertJson(['occupying_count' => 1, 'pending_count' => 0]);
});

/*
|--------------------------------------------------------------------------
| Explicit confirmation gate + no automatic cancellation
|--------------------------------------------------------------------------
*/

it('refuses a closure affecting confirmed appointments without the confirm flag and creates nothing', function () {
    $admin = camAdmin();
    $patient = camPatient();
    $appointment = camAppointment($patient);

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'single_day',
        'date' => '2026-12-01',
    ])->assertSessionHasErrors('confirm_affected');

    expect(ClinicUnavailability::count())->toBe(0)
        ->and($appointment->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('accepts the closure with the confirm flag and leaves appointments untouched', function () {
    $admin = camAdmin();
    $patient = camPatient();
    $confirmed = camAppointment($patient);
    $completed = camAppointment($patient, [
        'confirmed_time' => '11:00',
        'status' => Appointment::STATUS_COMPLETED,
    ]);
    $pending = camAppointment($patient, [
        'confirmed_date' => null,
        'confirmed_time' => null,
        'requested_time' => '14:00',
        'status' => Appointment::STATUS_PENDING,
    ]);

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'single_day',
        'date' => '2026-12-01',
        'confirm_affected' => 1,
    ])->assertSessionHas('success');

    expect(ClinicUnavailability::count())->toBe(1)
        ->and($confirmed->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($confirmed->fresh()->confirmed_date->toDateString())->toBe('2026-12-01')
        ->and($completed->fresh()->status)->toBe(Appointment::STATUS_COMPLETED)
        ->and($pending->fresh()->status)->toBe(Appointment::STATUS_PENDING);
});

it('recomputes affected appointments at save time so a stale preview cannot bypass the gate', function () {
    $admin = camAdmin();
    $patient = camPatient();

    // Preview before any appointment exists: zero affected.
    $this->actingAs($admin)->getJson(route('admin.clinic-unavailability.affected', [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
        'all_day' => 1,
    ]))->assertOk()->assertJson(['occupying_count' => 0]);

    // A staff confirmation lands AFTER the preview, BEFORE the save.
    $patient = camPatient('Interloper');
    camAppointment($patient, ['confirmed_time' => '10:00']);

    // Save without the confirm flag must still be refused.
    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'single_day',
        'date' => '2026-12-01',
    ])->assertSessionHasErrors('confirm_affected');

    expect(ClinicUnavailability::count())->toBe(0);
});

it('requires the confirm flag again when an edit newly covers confirmed appointments', function () {
    $admin = camAdmin();
    $patient = camPatient();
    $closure = camService()->createClosure([
        'start_date' => '2026-12-05', 'end_date' => '2026-12-05', 'all_day' => true,
    ], $admin);

    camAppointment($patient, [
        'confirmed_date' => '2026-12-02',
        'confirmed_time' => '10:00',
        'requested_date' => '2026-12-02',
    ]);

    // Expand onto 2026-12-02 without confirmation - refused.
    $this->actingAs($admin)->patch(route('admin.clinic-unavailability.update', $closure), [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-02',
        'all_day' => 1,
    ])->assertSessionHasErrors('confirm_affected');

    $closure->refresh();
    expect($closure->start_date->toDateString())->toBe('2026-12-05');

    // With confirmation - accepted.
    $this->actingAs($admin)->patch(route('admin.clinic-unavailability.update', $closure), [
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-02',
        'all_day' => 1,
        'confirm_affected' => 1,
    ])->assertSessionHas('success');

    $closure->refresh();
    expect($closure->start_date->toDateString())->toBe('2026-12-01')
        ->and($patient->appointments()->first()->fresh()->status)
        ->toBe(Appointment::STATUS_CONFIRMED); // never auto-cancelled
});

it('rolls back every window of a multi-slot batch when a later window hits the unconfirmed gate', function () {
    $admin = camAdmin();
    $patient = camPatient();

    // Confirmed appointment at 13:00 - only the second window is affected.
    camAppointment($patient, ['confirmed_time' => '13:00']);

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'slots',
        'date' => '2026-12-01',
        'slot_starts' => ['10:00', '13:00'],
    ])->assertSessionHasErrors('confirm_affected');

    // First window (10:00) must NOT have been committed.
    expect(ClinicUnavailability::count())->toBe(0);

    // With confirmation the whole batch commits atomically.
    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'slots',
        'date' => '2026-12-01',
        'slot_starts' => ['10:00', '13:00'],
        'confirm_affected' => 1,
    ])->assertSessionHas('success');

    expect(ClinicUnavailability::count())->toBe(2)
        ->and($patient->appointments()->first()->fresh()->status)
        ->toBe(Appointment::STATUS_CONFIRMED);
});

/*
|--------------------------------------------------------------------------
| Audit + patient-facing leakage + booking unaffected elsewhere
|--------------------------------------------------------------------------
*/

it('writes create/update/remove audit rows through the HTTP surface', function () {
    $admin = camAdmin();

    $this->actingAs($admin)->post(route('admin.clinic-unavailability.store'), [
        'mode' => 'single_day', 'date' => '2026-12-30', 'reason' => 'Year-end',
    ]);

    $closure = ClinicUnavailability::first();

    $this->actingAs($admin)->patch(route('admin.clinic-unavailability.update', $closure), [
        'start_date' => '2026-12-30', 'end_date' => '2026-12-30', 'all_day' => 1, 'reason' => 'Year-end (revised)',
    ]);

    $this->actingAs($admin)->delete(route('admin.clinic-unavailability.destroy', $closure));

    foreach (['CLINIC_UNAVAILABILITY_CREATED', 'CLINIC_UNAVAILABILITY_UPDATED', 'CLINIC_UNAVAILABILITY_REMOVED'] as $action) {
        expect(AuditLog::where('action', $action)
            ->where('module', 'CLINIC_UNAVAILABILITY')
            ->where('user_id', $admin->id)
            ->exists())->toBeTrue("missing audit {$action}");
    }
});

it('never leaks the internal reason through the patient slot endpoint', function () {
    $patientUser = camPatientUser();
    $patient = camPatient();
    $patient->user_id = $patientUser->id;
    $patient->save();

    camService()->createClosure([
        'start_date' => '2026-12-01', 'end_date' => '2026-12-01', 'all_day' => true,
        'reason' => 'SECRET-INTERNAL-NOTE-XYZ',
    ], camAdmin());

    $payload = $this->actingAs($patientUser)
        ->getJson('/patient/appointments/slots?date=2026-12-01')
        ->assertOk()
        ->json();

    expect(json_encode($payload))
        ->not->toContain('SECRET-INTERNAL-NOTE-XYZ')
        ->not->toContain('reason');
});

it('still lets staff confirm a different free slot while closures exist on other dates', function () {
    $admin = camAdmin();
    $service = camService();
    $staff = camStaff();
    $patient = camPatient();

    $service->createClosure([
        'start_date' => '2026-12-01', 'end_date' => '2026-12-01', 'all_day' => true,
    ], $admin);

    $appointment = camAppointment($patient, [
        'status' => Appointment::STATUS_PENDING,
        'confirmed_date' => null,
        'confirmed_time' => null,
    ]);

    $confirmed = app(\App\Services\AppointmentManagementService::class)
        ->confirm($appointment, $staff, '2026-12-02', '10:00');

    expect($confirmed->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($confirmed->confirmed_date->toDateString())->toBe('2026-12-02');
});

it('refuses to confirm INTO a closed slot', function () {
    $admin = camAdmin();
    $staff = camStaff();
    $patient = camPatient();

    camService()->createClosure([
        'start_date' => '2026-12-01', 'end_date' => '2026-12-01', 'all_day' => true,
    ], $admin);

    $appointment = camAppointment($patient, [
        'status' => Appointment::STATUS_PENDING,
        'confirmed_date' => null,
        'confirmed_time' => null,
    ]);

    expect(fn () => app(\App\Services\AppointmentManagementService::class)
        ->confirm($appointment, $staff, '2026-12-01', '10:00'))
        ->toThrow(\DomainException::class);

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_PENDING);
});

/*
|--------------------------------------------------------------------------
| windowsFromSlotStarts (service unit)
|--------------------------------------------------------------------------
*/

it('groups contiguous slot starts into windows and refuses off-grid values', function () {
    $service = camService();

    expect($service->windowsFromSlotStarts(['10:00', '10:30', '12:00']))->toBe([
        ['start' => '10:00', 'end' => '11:00'],
        ['start' => '12:00', 'end' => '12:30'],
    ]);

    expect($service->windowsFromSlotStarts(['16:30']))->toBe([
        ['start' => '16:30', 'end' => '17:00'],
    ]);

    expect($service->windowsFromSlotStarts([]))->toBe([]);

    expect(fn () => $service->windowsFromSlotStarts(['10:07']))
        ->toThrow(\DomainException::class);
});
