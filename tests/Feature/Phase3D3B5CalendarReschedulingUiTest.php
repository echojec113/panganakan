<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentManagementService;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Phase 3D.3B Step 5 - Admin/Staff Calendar Rescheduling UI
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the calendar-side
| delivery of Phase 3D.3B: type-aware pending change-request indicators
| (month chip "!", day badges, year month-card marker, legend entries)
| with exactly TWO batched appointment_change_requests reads per view
| (never N+1); the pending-reschedule details block (current vs proposed
| schedule labels, optional reason, submission timestamp, server-built
| review URLs, date-only delivery never inventing a time); the review
| action buttons + approve/decline forms that REUSE the existing Phase
| 3D.2C PATCH routes (approve moves the appointment to the proposed
| schedule exactly, decline leaves the confirmed schedule untouched);
| cancellation-panel regression (blocks are mutually exclusive); All
| Statuses default + filter preservation across Month/Year/Day; auth
| boundaries (guest redirect, patient 403 on the calendar itself - no
| patient request details are reachable from clinic-only routes);
| stale/invalid review handling (backend error rendered, correct modal
| reopened via errorAppointmentId, typed review note preserved);
| and the optional review note surviving a validation failure.
|
| The appointment STATUS is never conflated with the change-request
| STATUS: every marker/badge signals "review pending" only, while the
| chip color/status label stay driven by the appointment row itself.
*/

function phase3d3b5User(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d3b5Patient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 29,
        'email' => "phase3d3b5{$sequence}@portal.test",
        'contact_number' => '09179876543',
        'address_line' => '12 Bonifacio Street',
        'barangay' => 'San Isidro',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d3b5Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3d3b5Eligible(): array
{
    $user = phase3d3b5User('patient');
    $patient = phase3d3b5Patient();
    phase3d3b5Link($patient, $user);

    return [$user, $patient];
}

function phase3d3b5ConfirmedAppointment(Patient $patient, array $overrides = []): Appointment
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
        'reviewed_by' => phase3d3b5User('staff')->id,
        'reviewed_at' => now(),
    ], $overrides));
}

function phase3d3b5DeliveryAppointment(Patient $patient, array $overrides = []): Appointment
{
    return phase3d3b5ConfirmedAppointment($patient, array_merge([
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
        'confirmed_date' => '2026-11-10',
        'confirmed_time' => null,
    ], $overrides));
}

/** Pending reschedule request created through the real patient service path. */
function phase3d3b5RescheduleRequest(
    Appointment $appointment,
    Patient $patient,
    string $date = '2026-11-20',
    ?string $time = '10:00',
    ?string $reason = 'Work conflict, please move my visit.',
): AppointmentChangeRequest {
    $portalUser = User::query()->find($patient->user_id) ?? phase3d3b5User('patient');

    return app(AppointmentManagementService::class)
        ->requestAppointmentReschedule($appointment, $patient, $portalUser, $date, $time, $reason);
}

/** Pending cancellation request created through the real patient service path. */
function phase3d3b5CancellationRequest(
    Appointment $appointment,
    Patient $patient,
    string $reason = 'Family emergency, please cancel.',
): AppointmentChangeRequest {
    $portalUser = User::query()->find($patient->user_id) ?? phase3d3b5User('patient');

    return app(AppointmentManagementService::class)
        ->requestAppointmentCancellation($appointment, $patient, $portalUser, $reason);
}

/** Day-view URL for an appointment's confirmed date (no status param). */
function phase3d3b5DayUrl(string $date): string
{
    return route('appointments.index', [
        'view' => 'day',
        'date' => $date,
        'status' => 'all',
    ]);
}

/** Extract a server-built review URL of the given kind from a page. */
function phase3d3b5ExtractUrl(string $content, string $key): ?string
{
    // The details payload also carries the Phase 3B.2B appointment URLs
    // (appointments.decline shares the "declineUrl" key), so the change-
    // request variant is matched by its path segment.
    $pattern = '#' . preg_quote($key, '#') . '":"([^"]*appointment-change-requests[^"]*)"#';

    return preg_match($pattern, $content, $matches) === 1 ? $matches[1] : null;
}

/*
|--------------------------------------------------------------------------
| Type-aware pending indicators (month / year / day + legend)
|--------------------------------------------------------------------------
*/

it('flags a pending reschedule request on month chips with a type-aware label', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5ConfirmedAppointment($patient);
    phase3d3b5RescheduleRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)
        ->get(route('appointments.index', ['date' => '2026-11-07']))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('"pendingChangeRequestType":"reschedule"')
        ->toContain('"hasPendingChangeRequest":true')
        ->toContain('Reschedule request pending review')
        ->toContain('Cancellation request pending')
        ->toContain('Reschedule request pending');
});

it('renders type-aware day badges and keeps appointment status separate from request status', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5CancellationRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    phase3d3b5RescheduleRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-07'))
        ->assertOk()
        ->getContent();

    // Both badges are rendered server-side, type-aware, while the chips
    // themselves keep the CONFIRMED status styling (class="cal-event
    // confirmed") - the request status never recolors the appointment.
    expect($content)
        ->toContain('bg-orange-100 px-3 py-1 text-xs font-bold text-orange-800')
        ->toContain('bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-800')
        ->toContain('A patient reschedule request is awaiting review')
        ->toContain('A patient cancellation request is awaiting review')
        ->toContain('"pendingChangeRequestType":"cancellation"')
        ->toContain('"pendingChangeRequestType":"reschedule"')
        // The request status never replaces the appointment status: both
        // rows stay confirmed in the payload while awaiting review.
        ->toContain('"status":"confirmed"');
});

it('carries a batched pending change-request marker on year month cards', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5RescheduleRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)
        ->get(route('appointments.index', ['view' => 'year', 'date' => '2026-11-07']))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('change request(s) pending review')
        ->toContain('· 1 change request pending review');
});

it('keeps All Statuses as the default and preserves filters across Month, Year and Day', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5RescheduleRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    $staff = phase3d3b5User('staff');

    // No status parameter: All Statuses stays the default selection.
    $month = $this->actingAs($staff)
        ->get(route('appointments.index', ['date' => '2026-11-07']))
        ->assertOk()
        ->getContent();
    expect($month)->toContain('value="all" selected');

    // An explicit status+type selection survives every view switch link.
    foreach (['month', 'year', 'day'] as $view) {
        $content = $this->actingAs($staff)
            ->get(route('appointments.index', [
                'view' => $view,
                'date' => '2026-11-07',
                'status' => 'confirmed',
                'type' => 'checkup',
            ]))
            ->assertOk()
            ->getContent();

        expect($content)
            ->toContain('value="confirmed" selected')
            ->toContain('value="checkup" selected')
            ->and(substr_count($content, 'status=confirmed'))->toBeGreaterThanOrEqual(1)
            ->and(substr_count($content, 'type=checkup'))->toBeGreaterThanOrEqual(1);
    }

    // The month view keeps the stronger guarantee already asserted in
    // Phase 3B.2A: every period/view link carries both filters forward.
    $monthFiltered = $this->actingAs($staff)
        ->get(route('appointments.index', [
            'view' => 'month',
            'date' => '2026-11-07',
            'status' => 'confirmed',
            'type' => 'checkup',
        ]))
        ->assertOk()
        ->getContent();

    expect(substr_count($monthFiltered, 'status=confirmed'))->toBeGreaterThanOrEqual(4)
        ->and(substr_count($monthFiltered, 'type=checkup'))->toBeGreaterThanOrEqual(4);
});

/*
|--------------------------------------------------------------------------
| Details modal payload (current vs proposed, reason, URLs, date-only)
|--------------------------------------------------------------------------
*/

it('embeds the pending reschedule block with both schedules, reason and review URLs', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5ConfirmedAppointment($patient);
    $request = phase3d3b5RescheduleRequest(
        phase3d3b5ConfirmedAppointment($patient),
        $patient,
        '2026-11-20',
        '10:00',
        'School event, please move my visit.',
    );
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-07'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('"rescheduleRequest"')
        ->toContain('"cancellationRequest":null')
        ->toContain('"currentDateLabel":"Nov 7, 2026"')
        ->toContain('"currentTimeLabel":"14:00"')
        ->toContain('"proposedDateLabel":"Nov 20, 2026"')
        ->toContain('"proposedTimeLabel":"10:00"')
        ->toContain('School event, please move my visit.')
        ->toContain(route('appointment-change-requests.approve', ['changeRequest' => $request->id]))
        ->toContain(route('appointment-change-requests.decline', ['changeRequest' => $request->id]))
        ->toContain('Request type: Reschedule')
        ->toContain('Current Confirmed Schedule')
        ->toContain('Proposed Schedule');
});

it('stays date-only for delivery reschedules and never invents a clock time', function () {
    [, $patient] = phase3d3b5Eligible();
    $appointment = phase3d3b5DeliveryAppointment($patient);
    phase3d3b5RescheduleRequest($appointment, $patient, '2026-11-25', null, null);
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-10'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('"currentDateLabel":"Nov 10, 2026"')
        ->toContain('"currentTimeLabel":null')
        ->toContain('"proposedDateLabel":"Nov 25, 2026"')
        ->toContain('"proposedTimeLabel":null')
        ->toContain('"reason":null')
        ->not->toContain('"proposedTimeLabel":"00:00"');
});

/*
|--------------------------------------------------------------------------
| Review actions (buttons + forms reusing the existing PATCH routes)
|--------------------------------------------------------------------------
*/

it('renders approve and decline forms bound to the server-provided review URLs', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5RescheduleRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-07'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain("action = 'approveReschedule'")
        ->toContain("action = 'declineReschedule'")
        ->toContain(':action="selected?.rescheduleRequest?.approveUrl"')
        ->toContain(':action="selected?.rescheduleRequest?.declineUrl"')
        ->toContain('Approve & Move to Proposed Schedule')
        ->toContain('Decline Reschedule Request')
        ->toContain('id="approve-reschedule-note"')
        ->toContain('id="decline-reschedule-note"')
        // The confirmation copy explains the move before anything is posted.
        ->toContain('Approving moves this appointment to the proposed schedule above.')
        ->toContain('Declining keeps the original confirmed appointment schedule unchanged.')
        // Cancellation review forms remain untouched alongside them.
        ->toContain(':action="selected?.cancellationRequest?.approveUrl"')
        ->toContain(':action="selected?.cancellationRequest?.declineUrl"');
});

it('applies the proposed schedule exactly when the payload review URL is approved', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5ConfirmedAppointment($patient);
    $request = phase3d3b5RescheduleRequest(
        phase3d3b5ConfirmedAppointment($patient),
        $patient,
        '2026-11-20',
        '10:00',
    );
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-07'))
        ->assertOk()
        ->getContent();

    $approveUrl = phase3d3b5ExtractUrl($content, 'approveUrl');
    expect($approveUrl)->toBe(route('appointment-change-requests.approve', ['changeRequest' => $request->id]));

    $this->actingAs($staff)->patch($approveUrl, [
        'review_note' => 'Approved from the calendar panel.',
    ])->assertRedirect();

    $appointment = Appointment::query()->findOrFail($request->appointment_id);
    expect($appointment->confirmed_date->toDateString())->toBe('2026-11-20')
        ->and($appointment->confirmed_time)->toBe('10:00')
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($request->fresh()->status)->toBe(AppointmentChangeRequest::STATUS_APPROVED);
});

it('keeps the confirmed schedule untouched when the payload decline URL is used', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5ConfirmedAppointment($patient);
    $request = phase3d3b5RescheduleRequest(
        phase3d3b5ConfirmedAppointment($patient),
        $patient,
        '2026-11-20',
        '10:00',
    );
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-07'))
        ->assertOk()
        ->getContent();

    $declineUrl = phase3d3b5ExtractUrl($content, 'declineUrl');
    expect($declineUrl)->toBe(route('appointment-change-requests.decline', ['changeRequest' => $request->id]));

    $this->actingAs($staff)->patch($declineUrl, [
        'review_note' => 'Keeping the original slot.',
    ])->assertRedirect();

    $appointment = Appointment::query()->findOrFail($request->appointment_id);
    expect($appointment->confirmed_date->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00')
        ->and($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($request->fresh()->status)->toBe(AppointmentChangeRequest::STATUS_DECLINED)
        ->and($request->fresh()->review_note)->toBe('Keeping the original slot.');
});

it('keeps the cancellation review block mutually exclusive with the reschedule block', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5ConfirmedAppointment($patient);
    $request = phase3d3b5CancellationRequest(
        phase3d3b5ConfirmedAppointment($patient),
        $patient,
        'Work conflict, please cancel.',
    );
    $staff = phase3d3b5User('staff');

    $content = $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-07'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('"cancellationRequest"')
        ->toContain('"rescheduleRequest":null')
        ->toContain('Work conflict, please cancel.')
        ->toContain(route('appointment-change-requests.approve', ['changeRequest' => $request->id]))
        ->toContain('Pending Cancellation Request')
        ->toContain('"pendingChangeRequestType":"cancellation"')
        // No reschedule marker/badge is rendered for a cancellation-only day.
        ->not->toContain('Reschedule request pending review')
        ->not->toContain('A patient reschedule request is awaiting review');
});

/*
|--------------------------------------------------------------------------
| Authorization boundaries
|--------------------------------------------------------------------------
*/

it('redirects guests away from the calendar and review endpoints', function () {
    $this->get(route('appointments.index', ['view' => 'day', 'date' => '2026-11-07']))
        ->assertRedirect(route('login'));

    $this->patch('/appointment-change-requests/1/approve', ['review_note' => 'x'])
        ->assertRedirect(route('login'));

    $this->patch('/appointment-change-requests/1/decline', ['review_note' => 'x'])
        ->assertRedirect(route('login'));
});

it('forbids patients from the clinic calendar and its review endpoints', function () {
    $portalUser = phase3d3b5User('patient');
    $patient = phase3d3b5Patient();
    phase3d3b5Link($patient, $portalUser);

    // A real pending request gives the review routes an existing model id,
    // so the 403 proves the role boundary - not a 404 from binding.
    $request = phase3d3b5RescheduleRequest(
        phase3d3b5ConfirmedAppointment($patient),
        $patient,
    );

    $this->actingAs($portalUser)->get(phase3d3b5DayUrl('2026-11-07'))->assertForbidden();

    $this->actingAs($portalUser)->patch(
        route('appointment-change-requests.approve', ['changeRequest' => $request->id]),
        ['review_note' => 'Patient attempting self-review.']
    )->assertForbidden();

    $this->actingAs($portalUser)->patch(
        route('appointment-change-requests.decline', ['changeRequest' => $request->id]),
        ['review_note' => 'Patient attempting self-review.']
    )->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Stale / invalid review handling
|--------------------------------------------------------------------------
*/

it('renders the backend error and reopens the correct modal when a request is already reviewed', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5ConfirmedAppointment($patient);
    $request = phase3d3b5RescheduleRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    $staff = phase3d3b5User('staff');
    $calendarUrl = phase3d3b5DayUrl('2026-11-07');

    // First approval succeeds through the same route the form uses.
    $this->actingAs($staff)->from($calendarUrl)->patch(
        route('appointment-change-requests.approve', ['changeRequest' => $request->id]),
        ['review_note' => 'Approved once.']
    )->assertRedirect($calendarUrl);

    // Stale second approval: DomainException -> change_request error +
    // errorAppointmentId flashed, old input preserved (withInput()).
    $stale = $this->actingAs($staff)->from($calendarUrl)->patch(
        route('appointment-change-requests.approve', ['changeRequest' => $request->id]),
        ['review_note' => 'Stale note kept.']
    );
    $stale->assertRedirect($calendarUrl);
    $stale->assertSessionHasErrors('change_request');

    $content = $this->actingAs($staff)->get($calendarUrl)
        ->assertOk()
        ->getContent();

    expect($content)
        // The backend message is displayed clearly (never a silent no-op).
        ->toContain('The page has been refreshed with the latest state.')
        // The correct details modal is reopened for this appointment.
        ->toContain('$nextTick(() => open(' . $request->appointment_id . '));')
        // The typed review note survives the failed attempt.
        ->toContain('Stale note kept.');
});

it('preserves the typed review note when validation rejects it without reopening', function () {
    [, $patient] = phase3d3b5Eligible();
    phase3d3b5ConfirmedAppointment($patient);
    $request = phase3d3b5RescheduleRequest(phase3d3b5ConfirmedAppointment($patient), $patient);
    $staff = phase3d3b5User('staff');
    $calendarUrl = phase3d3b5DayUrl('2026-11-07');

    $response = $this->actingAs($staff)->from($calendarUrl)->patch(
        route('appointment-change-requests.approve', ['changeRequest' => $request->id]),
        ['review_note' => 'no']
    );

    $response->assertRedirect($calendarUrl);
    $response->assertSessionHasErrors('review_note');

    // Nothing was approved by the invalid submission.
    expect($request->fresh()->status)->toBe(AppointmentChangeRequest::STATUS_PENDING);

    $content = $this->actingAs($staff)->get($calendarUrl)->assertOk()->getContent();

    expect($content)
        // old() keeps the typed note in every review textarea.
        ->toContain('>no</textarea>')
        // The validation message is shown, but without an appointment id
        // there is no premature "open" action - the top flash carries it.
        ->not->toContain('$nextTick(() => open(' . $request->appointment_id . '));');
});

/*
|--------------------------------------------------------------------------
| Query-count regression (no N+1)
|--------------------------------------------------------------------------
*/

it('reads change requests with exactly two batched queries per day view', function () {
    $staff = phase3d3b5User('staff');

    foreach (range(1, 5) as $index) {
        [, $patient] = phase3d3b5Eligible();
        $appointment = phase3d3b5ConfirmedAppointment($patient, [
            'confirmed_time' => sprintf('%02d:00', 9 + $index),
        ]);

        if ($index % 2 === 0) {
            phase3d3b5CancellationRequest($appointment, $patient);
        } else {
            phase3d3b5RescheduleRequest($appointment, $patient);
        }
    }

    DB::enableQueryLog();
    $this->actingAs($staff)->get(phase3d3b5DayUrl('2026-11-07'))->assertOk();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $changeRequestReads = array_values(array_filter(
        $queries,
        fn (array $query) => str_contains($query['query'], 'appointment_change_requests')
    ));

    // Exactly two reads: one batched marker query (events grouped by
    // effective date) + one batched details query (review blocks).
    expect($changeRequestReads)->toHaveCount(2);
});
