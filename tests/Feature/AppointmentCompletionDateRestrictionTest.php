<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentManagementService;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| QA FIX 2 - "Mark Completed" scheduled-date restriction
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). A confirmed appointment may
| only be marked completed when its EFFECTIVE scheduled date
| (confirmed_date ?? requested_date) is today or earlier in Asia/Manila:
|
|   - calendar dates only - the appointment TIME is deliberately ignored,
|     so a later-today schedule is still completable today;
|   - the clinic-local (Asia/Manila) day decides, never the UTC day;
|   - the confirmed date always wins over the requested date;
|   - every other status keeps its existing workflow restriction;
|   - the calendar view hides the action using the same comparison while
|     the service re-validates authoritatively under the row lock.
*/

function completionGateAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Completion Gate Admin']);
}

function completionGatePatient(): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => "completiongate{$sequence}@clinic.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function completionGateConfirmed(Patient $patient, User $reviewer, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-10-17',
        'requested_time' => '10:00',
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_date' => '2026-10-17',
        'confirmed_time' => '10:00',
        'reviewed_by' => $reviewer->id,
        'reviewed_at' => '2026-10-01 08:00:00',
    ], $overrides));
}

/** PATCH helper that flashes back to the calendar (preserves view/date/filters). */
function completionGatePatch(User $user, Appointment $appointment)
{
    return test()->actingAs($user)
        ->from(route('appointments.index'))
        ->patch(route('appointments.complete', $appointment), []);
}

/** Cancel helper - mirrors the calendar's cancel form payload. */
function completionGateCancel(User $user, Appointment $appointment)
{
    return test()->actingAs($user)
        ->from(route('appointments.index'))
        ->patch(route('appointments.cancel', $appointment), [
            'cancellation_reason' => 'Patient requested a later date.',
        ]);
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function completionGateFreeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

/** Frozen-time tests must never leak into the rest of the suite. */
afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Server-side rule (authoritative - the UI gate can always be bypassed)
|--------------------------------------------------------------------------
*/

it('refuses to complete a future-dated confirmed appointment', function () {
    completionGateFreeze('2026-10-18 10:00:00'); // clinic today = October 18
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-30',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHasErrors('appointment');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and(AuditLog::where('action', 'APPOINTMENT_COMPLETED')->count())->toBe(0);
});

it('completes a confirmed appointment scheduled for today', function () {
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-18',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED)
        ->and(AuditLog::where('action', 'APPOINTMENT_COMPLETED')->count())->toBe(1);
});

it('completes a confirmed appointment scheduled for yesterday', function () {
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-17',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

it('completes an appointment later today because only calendar dates are compared', function () {
    // 16:30 is long past the fixture's 10:00 start - the rule must not look
    // at the clock time, only at the clinic calendar day.
    completionGateFreeze('2026-10-18 16:30:00');
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-18',
        'confirmed_time' => '10:00',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

/*
|--------------------------------------------------------------------------
| confirmed_date ?? requested_date (the confirmed date always wins)
|--------------------------------------------------------------------------
*/

it('refuses completion when only the confirmed date is in the future', function () {
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'requested_date' => '2026-10-01',
        'confirmed_date' => '2026-10-30',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHasErrors('appointment');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

it('completes when only the requested date is in the future', function () {
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'requested_date' => '2026-10-30', // superseded by the confirmation
        'confirmed_date' => '2026-10-17',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

/*
|--------------------------------------------------------------------------
| Asia/Manila calendar boundaries (never the UTC day)
|--------------------------------------------------------------------------
*/

it('treats a date as due as soon as it is today in Manila even when UTC lags', function () {
    // 2026-10-17 16:30 UTC == 2026-10-18 00:30 in Manila: the clinic day is
    // already October 18, so an October 18 appointment is due.
    Carbon::setTestNow(Carbon::parse('2026-10-17 16:30:00', 'UTC'));
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-18',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

it('refuses a date that is still tomorrow in Manila even though UTC already turned', function () {
    // 2026-10-17 15:00 UTC == 2026-10-17 23:00 in Manila: October 18 is
    // still tomorrow for the clinic, so the appointment is not due.
    Carbon::setTestNow(Carbon::parse('2026-10-17 15:00:00', 'UTC'));
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-18',
    ]);

    completionGatePatch($admin, $appointment)
        ->assertSessionHasErrors('appointment');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

/*
|--------------------------------------------------------------------------
| Existing lifecycle restrictions are untouched
|--------------------------------------------------------------------------
*/

it('keeps every non-confirmed status refusing completion', function () {
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();
    $patient = completionGatePatient();

    $pending = Appointment::forceCreate([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-10-17',
        'requested_time' => '10:00',
        'status' => Appointment::STATUS_PENDING,
    ]);
    $declined = completionGateConfirmed($patient, $admin, [
        'status' => Appointment::STATUS_DECLINED,
        'decline_reason' => 'Patient asked to rebook.',
    ]);
    $cancelled = completionGateConfirmed($patient, $admin, [
        'status' => Appointment::STATUS_CANCELLED,
        'cancellation_reason' => 'Clinic closure.',
    ]);
    $completed = completionGateConfirmed($patient, $admin, [
        'status' => Appointment::STATUS_COMPLETED,
    ]);

    foreach ([$pending, $declined, $cancelled, $completed] as $appointment) {
        completionGatePatch($admin, $appointment)
            ->assertSessionHasErrors('appointment');
    }

    expect($pending->refresh()->status)->toBe(Appointment::STATUS_PENDING)
        ->and($declined->refresh()->status)->toBe(Appointment::STATUS_DECLINED)
        ->and($cancelled->refresh()->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($completed->refresh()->status)->toBe(Appointment::STATUS_COMPLETED);
});

/*
|--------------------------------------------------------------------------
| Shared rule helper (single source of truth for view + service)
|--------------------------------------------------------------------------
*/

it('computes the completion-date rule from the clinic-local calendar day', function () {
    completionGateFreeze('2026-10-18 10:00:00');

    expect(AppointmentManagementService::completionNotDueIssue('2026-10-17'))->toBeNull()
        ->and(AppointmentManagementService::completionNotDueIssue('2026-10-18'))->toBeNull()
        ->and(AppointmentManagementService::completionNotDueIssue('2026-10-30'))->not->toBeNull()
        ->and(AppointmentManagementService::completionNotDueIssue('2026-10-30'))->toContain('future')
        ->and(AppointmentManagementService::completionNotDueIssue(null))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Calendar view gate (HTTP tests never execute Alpine, so the rendered
| source is asserted directly: payload field, Manila anchor, gate)
|--------------------------------------------------------------------------
*/

it('hands the panel the effective date, the Manila anchor and the due-date gate', function () {
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();
    $future = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-19',
    ]);

    $html = $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-10-19', 'status' => 'all']))
        ->assertOk()
        ->getContent();

    expect($future->confirmed_date?->toDateString())->toBe('2026-10-19');

    expect($html)
        // The effective scheduled date reaches Alpine as a plain Y-m-d string...
        ->toContain('"effectiveDateIso":"2026-10-19"')
        // ...compared against the server-rendered Asia/Manila day...
        ->toContain('const CLINIC_TODAY = "2026-10-18"')
        // ...through the gate on the Mark Completed BUTTON (not the container).
        ->toContain('x-show="isCompletionDue(selected?.effectiveDateIso)"');
});

/*
|--------------------------------------------------------------------------
| QA correction - Cancel is independent of the completion-date gate
|--------------------------------------------------------------------------
| The date gate must hide ONLY Mark Completed. Cancel keeps the original
| confirmed-status container condition so a future confirmed appointment
| still offers Cancel.
*/

it('keeps Cancel visible and gates only Mark Completed on the date rule', function () {
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();

    $html = $this->actingAs($admin)
        ->get(route('appointments.index', ['view' => 'day', 'date' => '2026-10-19', 'status' => 'all']))
        ->assertOk()
        ->getContent();

    expect($html)
        // The shared Cancel/Complete container keeps the original, date-free
        // confirmed-status condition (closing quote included, so the pending
        // cancellation/reschedule request rows do not satisfy this match)...
        ->toContain("!action && selected?.status === 'confirmed'\"")
        // ...the old combined gate that hid BOTH buttons is gone...
        ->not->toContain("status === 'confirmed' && isCompletionDue(")
        // ...Cancel is still wired to its original action...
        ->toContain("x-on:click=\"action = 'cancel'\"")
        // ...and Mark Completed alone carries the date gate.
        ->toContain('x-show="isCompletionDue(selected?.effectiveDateIso)"');
});

it('still cancels a future-dated confirmed appointment through the original backend rules', function () {
    // Staff cancellation has never carried a schedule-date restriction - only
    // the confirmed-status transition. The completion-date rule must not
    // leak into cancel().
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();
    $appointment = completionGateConfirmed(completionGatePatient(), $admin, [
        'confirmed_date' => '2026-10-30',
    ]);

    completionGateCancel($admin, $appointment)
        ->assertSessionHas('success');

    expect($appointment->refresh()->status)->toBe(Appointment::STATUS_CANCELLED)
        ->and($appointment->cancellation_reason)->toBe('Patient requested a later date.')
        ->and(AuditLog::where('action', 'APPOINTMENT_CANCELLED')->count())->toBe(1);
});

it('keeps the existing per-status action affordances rendered', function () {
    // Pending keeps Confirm/Decline; confirmed keeps Cancel (+ gated
    // Complete); the terminal statuses keep the "No further actions"
    // notice. All of these are static markup - Alpine only toggles them.
    completionGateFreeze('2026-10-18 10:00:00');
    $admin = completionGateAdmin();

    $html = $this->actingAs($admin)
        ->get(route('appointments.index'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain("x-show=\"!action && selected?.status === 'pending'\"")
        // Phase 4: the Confirm click also dispatches the slot-picker
        // sync so the picker can exact-match preselect the requested
        // time against the live availability response.
        ->toContain("x-on:click=\"action = 'confirm'; if (selected?.type !== 'delivery_request')")
        ->toContain("x-on:click=\"action = 'decline'\"")
        ->toContain("x-on:click=\"action = 'cancel'\"")
        ->toContain("['completed', 'cancelled', 'declined'].includes(selected?.status)")
        ->toContain('No further actions');
});
