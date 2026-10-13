<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Patient;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Phase 3D.4B - Pre-migration Security/UI Hardening
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Closes the three Medium
| audit findings BEFORE any schema work:
|
| P1 - The four Patient Portal appointment WRITE routes now share ONE
|      named rate limiter ('patient-appointment-writes', 10 per minute
|      keyed per authenticated user). GET reads, Admin/Staff calendar
|      management routes and the notification read actions are
|      deliberately outside the limiter.
|
| P2 - A cancellation refusal (FormRequest validation OR service
|      DomainException) is scoped to the ONE card and the ONE form it
|      targeted, and cancellation old input is WHITELISTED to the
|      reason field so a forged POST can never park a password or a
|      foreign field in the session.
|
| P3 - Appointment and AppointmentChangeRequest $fillable are narrowed
|      to the patient-supplied content fields only, so mass assignment
|      can never touch ownership, source, status, the confirmed
|      schedule or reviewer columns.
|
| No migration, no MySQL and no schema change in this sprint.
*/

function phase3d4bUser(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d4bPatient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 29,
        'email' => "phase3d4b{$sequence}@portal.test",
        'contact_number' => '09179876543',
        'address_line' => '12 Bonifacio Street',
        'barangay' => 'San Isidro',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d4bLink(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3d4bPortal(): array
{
    $user = phase3d4bUser('patient');
    $patient = phase3d4bPatient();
    phase3d4bLink($patient, $user);

    return [$user, $patient];
}

function phase3d4bPending(Patient $patient, array $overrides = []): Appointment
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

function phase3d4bConfirmed(Patient $patient, array $overrides = []): Appointment
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
        'reviewed_by' => phase3d4bUser('staff')->id,
        'reviewed_at' => now(),
    ], $overrides));
}

/** POST a cancellation write exactly as the portal form does (from the index). */
function phase3d4bCancelPost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.index'))
        ->post(route('patient.appointments.cancel', ['appointmentId' => $appointment->id]), $data);
}

/** POST a cancellation REQUEST exactly as the portal form does (from the index). */
function phase3d4bRequestPost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.index'))
        ->post(route('patient.appointments.cancellationRequest', ['appointmentId' => $appointment->id]), $data);
}

/** POST a reschedule REQUEST exactly as the portal form does (from the index). */
function phase3d4bReschedulePost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.index'))
        ->post(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $appointment->id]),
            array_merge(['form' => 'reschedule'], $data)
        );
}

/** Inner text of the textarea whose id attribute matches, or null when absent. */
function phase3d4bTextareaValue(string $content, string $id): ?string
{
    $pattern = '/id="' . preg_quote($id, '/') . '"[^>]*>(.*?)<\/textarea>/s';

    return preg_match($pattern, $content, $matches) === 1 ? $matches[1] : null;
}

/** Render the shared portal page as the given patient. */
function phase3d4bIndexContent(User $user): string
{
    return test()->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();
}

/** Burn the shared write budget with validation failures (no rows created). */
function phase3d4bSpendBudget(User $user, int $attempts = 10): void
{
    for ($attempt = 0; $attempt < $attempts; $attempt++) {
        test()->actingAs($user)
            ->post(route('patient.appointments.store'), [])
            ->assertStatus(302);
    }
}

/*
|--------------------------------------------------------------------------
| P1 - Patient Portal appointment write throttling
|--------------------------------------------------------------------------
*/

it('returns 429 once a patient exhausts the shared appointment write budget', function () {
    [$user] = phase3d4bPortal();

    phase3d4bSpendBudget($user);

    // The budget is SHARED across all four write endpoints, so a
    // different write route is refused too (not just the one that was
    // used to burn the budget).
    test()->actingAs($user)
        ->post(route('patient.appointments.cancel', ['appointmentId' => 1]), [])
        ->assertStatus(429);

    test()->actingAs($user)
        ->post(route('patient.appointments.cancellationRequest', ['appointmentId' => 1]), [])
        ->assertStatus(429);

    test()->actingAs($user)
        ->post(route('patient.appointments.rescheduleRequest', ['appointmentId' => 1]), [])
        ->assertStatus(429);

    test()->actingAs($user)
        ->post(route('patient.appointments.store'), [])
        ->assertStatus(429);
});

it('keys the write budget per authenticated user, not per IP address', function () {
    [$firstUser] = phase3d4bPortal();
    [$secondUser] = phase3d4bPortal();

    phase3d4bSpendBudget($firstUser);

    test()->actingAs($firstUser)
        ->post(route('patient.appointments.store'), [])
        ->assertStatus(429);

    // Same request IP (127.0.0.1 in tests), different signed-in patient:
    // the second patient's budget is untouched.
    test()->actingAs($secondUser)
        ->post(route('patient.appointments.store'), [])
        ->assertStatus(302);
});

it('does not throttle the patient appointment read routes', function () {
    [$user, $patient] = phase3d4bPortal();
    phase3d4bPending($patient);

    phase3d4bSpendBudget($user);

    test()->actingAs($user)
        ->post(route('patient.appointments.store'), [])
        ->assertStatus(429);

    test()->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk();

    test()->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk();
});

it('leaves the patient notification reads and Admin/Staff management routes outside the limiter', function () {
    [$user, $patient] = phase3d4bPortal();
    phase3d4bPending($patient);

    phase3d4bSpendBudget($user);

    // Patient Portal notification reads are not part of the write budget.
    test()->actingAs($user)
        ->get(route('patient.notifications.index'))
        ->assertOk();

    // Admin/Staff calendar management is a different surface entirely.
    test()->actingAs(phase3d4bUser('admin'))
        ->get(route('appointments.index'))
        ->assertOk();
});

it('refuses an unauthenticated guest before the limiter is consulted', function () {
    $response = test()->post(route('patient.appointments.store'), []);

    // 'auth' runs first (framework middleware priority), so a guest is
    // redirected to login and never enters - or consumes - the limiter.
    $response->assertRedirect();

    expect($response->getStatusCode())->not->toBe(429);
});

/*
|--------------------------------------------------------------------------
| P2 - cancellation error and old-input scoping
|--------------------------------------------------------------------------
*/

it('scopes a direct-cancel refusal to the targeted card and form', function () {
    [$user, $patient] = phase3d4bPortal();
    $target = phase3d4bPending($patient);
    $other = phase3d4bPending($patient, ['requested_date' => '2026-12-05', 'requested_time' => '11:00']);

    phase3d4bCancelPost($user, $target, ['reason' => 'no'])
        ->assertRedirect(route('patient.appointments.index'))
        ->assertSessionHasErrors('reason')
        ->assertSessionHas('errorAppointmentId', $target->id)
        ->assertSessionHas('errorForm', 'cancel');

    $content = phase3d4bIndexContent($user);

    // The refusal text is painted exactly once page-wide.
    expect(substr_count($content, 'must be at least 3 characters'))->toBe(1)
        // The typed reason is repopulated ONLY on the matching form.
        ->and(phase3d4bTextareaValue($content, 'cancel-reason-' . $target->id))->toBe('no')
        ->and(phase3d4bTextareaValue($content, 'cancel-reason-' . $other->id))->toBe('');
});

it('never paints a direct-cancel refusal onto the cancellation-request form', function () {
    [$user, $patient] = phase3d4bPortal();
    $pending = phase3d4bPending($patient);
    $confirmed = phase3d4bConfirmed($patient, ['requested_date' => '2026-12-06', 'requested_time' => '09:00']);

    phase3d4bCancelPost($user, $pending, ['reason' => 'no'])
        ->assertSessionHasErrors('reason')
        ->assertSessionHas('errorAppointmentId', $pending->id)
        ->assertSessionHas('errorForm', 'cancel');

    $content = phase3d4bIndexContent($user);

    expect(substr_count($content, 'must be at least 3 characters'))->toBe(1)
        ->and(phase3d4bTextareaValue($content, 'cancel-reason-' . $pending->id))->toBe('no')
        ->and(phase3d4bTextareaValue($content, 'cancel-request-reason-' . $confirmed->id))->toBe('');
});

it('scopes a cancellation-request refusal to its own form', function () {
    [$user, $patient] = phase3d4bPortal();
    $confirmed = phase3d4bConfirmed($patient);
    $pending = phase3d4bPending($patient, ['requested_date' => '2026-12-05', 'requested_time' => '11:00']);

    phase3d4bRequestPost($user, $confirmed, ['reason' => 'no'])
        ->assertSessionHasErrors('reason')
        ->assertSessionHas('errorAppointmentId', $confirmed->id)
        ->assertSessionHas('errorForm', 'cancellation-request');

    $content = phase3d4bIndexContent($user);

    expect(substr_count($content, 'must be at least 3 characters'))->toBe(1)
        ->and(phase3d4bTextareaValue($content, 'cancel-request-reason-' . $confirmed->id))->toBe('no')
        ->and(phase3d4bTextareaValue($content, 'cancel-reason-' . $pending->id))->toBe('');
});

it('scopes a service DomainException refusal to the targeted card', function () {
    [$user, $patient] = phase3d4bPortal();
    $confirmed = phase3d4bConfirmed($patient);
    $pending = phase3d4bPending($patient, ['requested_date' => '2026-12-05', 'requested_time' => '11:00']);

    // A confirmed appointment can never be cancelled DIRECTLY: the
    // service refuses it under lock.
    $response = phase3d4bCancelPost($user, $confirmed, ['reason' => 'Family is travelling abroad this month.']);

    $response
        ->assertSessionHasErrors('reason')
        ->assertSessionHas('errorAppointmentId', $confirmed->id)
        ->assertSessionHas('errorForm', 'cancel');

    $message = $response->getSession()->get('errors')->first('reason');

    // Assert on a quote-free fragment: Blade escapes the double quotes
    // around the raw status word, so the raw message never matches.
    $needle = 'This appointment can no longer be cancelled';

    $content = phase3d4bIndexContent($user);

    // Painted once - and only as the card-level fallback, because the
    // direct-cancel form does not render on a confirmed card.
    expect($message)->toContain('current status is')
        ->and(substr_count($content, $needle))->toBe(1)
        ->and(phase3d4bTextareaValue($content, 'cancel-reason-' . $pending->id))->toBe('');
});

it('keeps a reschedule failure off both cancellation forms', function () {
    [$user, $patient] = phase3d4bPortal();
    $confirmed = phase3d4bConfirmed($patient);

    phase3d4bReschedulePost($user, $confirmed, [
        'proposed_date' => '2026-12-20',
        'proposed_time' => '10:00',
        'reason' => 'no',
    ])
        ->assertSessionHasErrors('reason')
        ->assertSessionMissing('errorForm');

    $content = phase3d4bIndexContent($user);

    // The reason error belongs to the reschedule form only, and the
    // cancellation textareas never repaint it.
    expect(substr_count($content, 'must be at least 3 characters'))->toBe(1)
        ->and(phase3d4bTextareaValue($content, 'cancel-request-reason-' . $confirmed->id))->toBe('')
        ->and(phase3d4bTextareaValue($content, 'reschedule-reason-' . $confirmed->id))->toBe('no');
});

it('whitelists cancellation old input to the reason field on a validation failure', function () {
    [$user, $patient] = phase3d4bPortal();
    $pending = phase3d4bPending($patient);

    phase3d4bCancelPost($user, $pending, [
        'reason' => 'no',
        'password' => 'leaked-secret',
        'password_confirmation' => 'leaked-secret',
        'patient_id' => $patient->id + 1000,
        'status' => 'confirmed',
        'confirmed_date' => '2026-01-01',
        'reviewed_by' => 999999,
    ])
        ->assertSessionHasErrors('reason')
        ->assertSessionHasInput('reason', 'no');

    $old = $this->app['session.store']->get('_old_input');

    expect($old)->not->toHaveKey('password')
        ->and($old)->not->toHaveKey('password_confirmation')
        ->and($old)->not->toHaveKey('patient_id')
        ->and($old)->not->toHaveKey('status')
        ->and($old)->not->toHaveKey('confirmed_date')
        ->and($old)->not->toHaveKey('reviewed_by');
});

it('whitelists cancellation old input to the reason field on a service refusal', function () {
    [$user, $patient] = phase3d4bPortal();
    $confirmed = phase3d4bConfirmed($patient);

    phase3d4bCancelPost($user, $confirmed, [
        'reason' => 'Family is travelling abroad this month.',
        'password' => 'leaked-secret',
        'patient_id' => $patient->id + 1000,
        'status' => 'confirmed',
    ])
        ->assertSessionHasErrors('reason')
        ->assertSessionHas('errorForm', 'cancel');

    $old = $this->app['session.store']->get('_old_input');

    expect($old)->not->toHaveKey('password')
        ->and($old)->not->toHaveKey('patient_id')
        ->and($old)->not->toHaveKey('status')
        ->and($old['reason'])->toBe('Family is travelling abroad this month.');
});

it('whitelists cancellation-request old input to the reason field on both failure paths', function () {
    [$user, $patient] = phase3d4bPortal();
    $confirmed = phase3d4bConfirmed($patient);

    // Validation path.
    phase3d4bRequestPost($user, $confirmed, [
        'reason' => 'no',
        'password' => 'leaked-secret',
        'patient_id' => $patient->id + 1000,
    ])->assertSessionHasErrors('reason');

    $old = $this->app['session.store']->get('_old_input');

    expect($old)->not->toHaveKey('password')
        ->and($old)->not->toHaveKey('patient_id');

    // Service refusal path for the same endpoint: a second pending
    // change request is refused under lock.
    app(\App\Services\AppointmentManagementService::class)
        ->requestAppointmentCancellation($confirmed, $patient, $user, 'Family emergency, please cancel.');

    phase3d4bRequestPost($user, $confirmed, [
        'reason' => 'Family emergency again.',
        'password' => 'leaked-secret',
        'patient_id' => $patient->id + 1000,
    ])
        ->assertSessionHasErrors('reason')
        ->assertSessionHas('errorForm', 'cancellation-request')
        ->assertSessionHas('errorAppointmentId', $confirmed->id);

    $old = $this->app['session.store']->get('_old_input');

    expect($old)->not->toHaveKey('password')
        ->and($old)->not->toHaveKey('patient_id')
        ->and($old['reason'])->toBe('Family emergency again.');

    expect($confirmed->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);
});

/*
|--------------------------------------------------------------------------
| P3 - mass-assignment contract
|--------------------------------------------------------------------------
*/

it('ignores every server-owned column a forged mass assignment supplies', function () {
    [$user, $patient] = phase3d4bPortal();
    $foreign = phase3d4bPatient();
    $appointment = phase3d4bPending($patient);

    $appointment->fill([
        'patient_id' => $foreign->id,
        'source' => Appointment::SOURCE_WALK_IN,
        'status' => Appointment::STATUS_CONFIRMED,
        'confirmed_date' => '2027-01-01',
        'confirmed_time' => '08:00',
        'reviewed_by' => $user->id,
        'reviewed_at' => now(),
        'decline_reason' => 'Forged decline',
        'cancellation_reason' => 'Forged cancellation',
    ]);

    expect($appointment->isDirty())->toBeFalse()
        ->and($appointment->patient_id)->toBe($patient->id)
        ->and($appointment->source)->toBe(Appointment::SOURCE_ONLINE)
        ->and($appointment->status)->toBe(Appointment::STATUS_PENDING)
        ->and($appointment->confirmed_date)->toBeNull();

    $appointment->save();

    $fresh = Appointment::query()->findOrFail($appointment->id);

    expect($fresh->patient_id)->toBe($patient->id)
        ->and($fresh->status)->toBe(Appointment::STATUS_PENDING)
        ->and($fresh->confirmed_date)->toBeNull()
        ->and($fresh->reviewed_by)->toBeNull()
        ->and($fresh->decline_reason)->toBeNull()
        ->and($fresh->cancellation_reason)->toBeNull();
});

it('still mass-assigns the appointment content fields', function () {
    [, $patient] = phase3d4bPortal();
    $appointment = phase3d4bPending($patient);

    $appointment->fill([
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_date' => '2026-12-20',
        'requested_time' => null,
    ]);

    expect($appointment->isDirty())->toBeTrue()
        ->and($appointment->appointment_type)->toBe(Appointment::TYPE_DELIVERY_REQUEST)
        ->and($appointment->requested_date->toDateString())->toBe('2026-12-20');
});

it('ignores every review and ownership column on a forged change-request mass assignment', function () {
    [, $patient] = phase3d4bPortal();
    $appointment = phase3d4bConfirmed($patient);
    $foreign = phase3d4bConfirmed($patient, ['requested_date' => '2026-12-08', 'requested_time' => '15:00']);

    $request = AppointmentChangeRequest::forceCreate([
        'appointment_id' => $appointment->id,
        'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
        'reason' => 'Fixture reason for the hardening contract.',
        'status' => AppointmentChangeRequest::STATUS_PENDING,
    ]);

    $request->fill([
        'appointment_id' => $foreign->id,
        'type' => AppointmentChangeRequest::TYPE_RESCHEDULE,
        'status' => AppointmentChangeRequest::STATUS_APPROVED,
        'reviewed_by' => 999999,
        'reviewed_at' => now(),
        'review_note' => 'Forged approval note',
    ]);

    expect($request->isDirty())->toBeFalse()
        ->and($request->appointment_id)->toBe($appointment->id)
        ->and($request->type)->toBe(AppointmentChangeRequest::TYPE_CANCELLATION)
        ->and($request->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($request->reviewed_by)->toBeNull()
        ->and($request->review_note)->toBeNull();

    $request->save();

    $fresh = AppointmentChangeRequest::query()->findOrFail($request->id);

    expect($fresh->appointment_id)->toBe($appointment->id)
        ->and($fresh->type)->toBe(AppointmentChangeRequest::TYPE_CANCELLATION)
        ->and($fresh->status)->toBe(AppointmentChangeRequest::STATUS_PENDING)
        ->and($fresh->reviewed_by)->toBeNull()
        ->and($fresh->review_note)->toBeNull();
});

it('still mass-assigns the change-request proposal fields', function () {
    [, $patient] = phase3d4bPortal();
    $appointment = phase3d4bConfirmed($patient);

    $request = AppointmentChangeRequest::forceCreate([
        'appointment_id' => $appointment->id,
        'type' => AppointmentChangeRequest::TYPE_CANCELLATION,
        'reason' => 'Fixture reason for the hardening contract.',
        'status' => AppointmentChangeRequest::STATUS_PENDING,
    ]);

    $request->fill([
        'proposed_date' => '2026-12-24',
        'proposed_time' => '10:30',
        'reason' => 'Patient supplied proposal reason.',
    ]);

    expect($request->isDirty())->toBeTrue()
        ->and($request->proposed_date->toDateString())->toBe('2026-12-24')
        ->and($request->proposed_time)->toBe('10:30')
        ->and($request->reason)->toBe('Patient supplied proposal reason.');
});
