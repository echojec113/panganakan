<?php

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Phase 3D.3B Step 4 - Patient Portal Rescheduling UI
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Pure presentation slice:
| the backend contract is already regression-gated by
| Phase3D3BPatientRescheduleTest (Step 1) and
| Phase3D3B2StaffRescheduleReviewTest (Step 2). Covers the portal card:
| "Request Reschedule" eligibility (own online confirmed cards before
| the cutoff with no other pending change request; pending/walk-in/
| terminal/cutoff-passed cards stay read-only), the date-only delivery
| form vs the clinic-hours time field for checkup/counseling, the
| required confirmation note, CSRF + NO patient_id/source/status/
| ownership fields in the posted form, the type-aware pending badge
| (cancellation wording preserved verbatim, reschedule wording with the
| proposed schedule), the approved banner with the NEW confirmed
| schedule, the declined banner with the clinic explanation and the
| untouched original schedule, both change forms coexisting on one
| eligible card, foreign rows/names never leaking, section grouping and
| history pagination untouched, old input reopening the RIGHT
| disclosure (reschedule form marker) without leaking the reason into
| cancellation textareas, and a DomainException refusal rendered on the
| TARGETED card only (scoped by the flashed errorAppointmentId).
|
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3d3b4User(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3d3b4Patient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 29,
        'email' => "phase3d3b4{$sequence}@portal.test",
        'contact_number' => '09179876543',
        'address_line' => '12 Bonifacio Street',
        'barangay' => 'San Isidro',
        'city_municipality' => 'Manila',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3d3b4Link(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3d3b4Eligible(): array
{
    $user = phase3d3b4User('patient');
    $patient = phase3d3b4Patient();
    phase3d3b4Link($patient, $user);

    return [$user, $patient];
}

function phase3d3b4ConfirmedAppointment(Patient $patient, array $overrides = []): Appointment
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
        'reviewed_by' => phase3d3b4User('staff')->id,
        'reviewed_at' => now(),
    ], $overrides));
}

function phase3d3b4PendingAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-12-02',
        'requested_time' => '09:00',
        'status' => Appointment::STATUS_PENDING,
    ], $overrides));
}

/** POST as the real form does: proposed fields + the display-state marker. */
function phase3d3b4ReschedulePost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.index'))
        ->post(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $appointment->id]),
            array_merge(['form' => 'reschedule'], $data)
        );
}

function phase3d3b4CancellationRequestPost(User $user, Appointment $appointment, array $data)
{
    return test()->actingAs($user)
        ->from(route('patient.appointments.index'))
        ->post(
            route('patient.appointments.cancellationRequest', ['appointmentId' => $appointment->id]),
            $data
        );
}

function phase3d3b4Review(User $staff, string $routeName, AppointmentChangeRequest $request, array $data)
{
    return test()->actingAs($staff)
        ->patch(route($routeName, ['changeRequest' => $request->id]), $data);
}

/** The rendered HTML of THIS appointment's reschedule <form> (empty if absent). */
function phase3d3b4RescheduleFormSegment(string $content, Appointment $appointment): string
{
    $pattern = '/<form method="POST" action="[^"]*\/appointments\/'
        . $appointment->id
        . '\/reschedule-requests[^"]*".*?<\/form>/s';

    preg_match($pattern, $content, $matches);

    return $matches[0] ?? '';
}

/** The rendered HTML of THIS appointment's cancellation REQUEST <form>. */
function phase3d3b4CancellationFormSegment(string $content, Appointment $appointment): string
{
    $pattern = '/<form method="POST" action="[^"]*\/appointments\/'
        . $appointment->id
        . '\/cancellation-requests[^"]*".*?<\/form>/s';

    preg_match($pattern, $content, $matches);

    return $matches[0] ?? '';
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3d3b4Freeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Eligibility matrix for the Request Reschedule disclosure
|--------------------------------------------------------------------------
*/

it('offers the reschedule form only on eligible confirmed online cards', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();

    $eligible = phase3d3b4ConfirmedAppointment($patient);
    $walkIn = phase3d3b4ConfirmedAppointment($patient, ['source' => Appointment::SOURCE_WALK_IN]);
    // Confirmed but its scheduled start already passed - it renders in
    // History, past the cutoff, so no reschedule action.
    $pastStart = phase3d3b4ConfirmedAppointment($patient, [
        'confirmed_date' => '2026-10-11',
        'confirmed_time' => '10:00',
    ]);
    $pending = phase3d3b4PendingAppointment($patient);

    $response = $this->actingAs($user)->get(route('patient.appointments.index'));

    $response->assertOk();
    $content = $response->getContent();

    expect(substr_count($content, 'Request Reschedule'))->toBe(1)
        ->and($content)->toContain(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $eligible->id])
        )
        ->not->toContain(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $walkIn->id])
        )
        ->not->toContain(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $pastStart->id])
        )
        ->not->toContain(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $pending->id])
        );
});

it('offers no reschedule form on terminal-status cards', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();

    phase3d3b4ConfirmedAppointment($patient, [
        'status' => Appointment::STATUS_COMPLETED,
        'confirmed_date' => '2026-11-08',
        'confirmed_time' => '09:00',
    ]);
    phase3d3b4ConfirmedAppointment($patient, ['status' => Appointment::STATUS_CANCELLED]);
    phase3d3b4ConfirmedAppointment($patient, ['status' => Appointment::STATUS_DECLINED]);

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertDontSee('Request Reschedule');
});

it('hides both change forms while a cancellation request is pending', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);

    phase3d3b4CancellationRequestPost($user, $appointment, [
        'reason' => 'Travel conflict, please review.',
    ])->assertSessionHas('success');

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Cancellation request pending')
        ->assertDontSee('Request Reschedule')
        ->assertDontSee('Request Cancellation')
        ->assertDontSee('Cancel Appointment');
});

/*
|--------------------------------------------------------------------------
| Form markup: fields, constraints, note, CSRF, no ownership fields
|--------------------------------------------------------------------------
*/

it('renders the reschedule form fields with clinic-hours constraints and no ownership fields', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    $form = phase3d3b4RescheduleFormSegment($content, $appointment);

    expect($form)->not->toBe('');

    // Required note, exactly as specified.
    expect($form)->toContain(
        'Your current appointment will remain confirmed until the clinic approves your reschedule request.'
    );

    // Display-state marker (NOT an ownership/status field).
    expect($form)->toContain('name="form"')
        ->toContain('value="reschedule"');

    // CSRF.
    expect($form)->toContain('name="_token"');

    // Proposed date: required, Manila-today floor.
    expect($form)
        ->toContain('name="proposed_date"')
        ->toContain('type="date"')
        ->toContain('min="' . now(\App\Services\AppointmentManagementService::CLINIC_TIMEZONE)->toDateString() . '"')
        ->toContain('required');

    // Proposed time: required, clinic hours 08:00 up to before 17:00.
    expect($form)
        ->toContain('name="proposed_time"')
        ->toContain('type="time"')
        ->toContain('min="08:00"')
        ->toContain('max="16:59"');

    // Optional reason with length bounds.
    expect($form)
        ->toContain('name="reason"')
        ->toContain('minlength="3"')
        ->toContain('maxlength="1000"');

    // Server-owned fields are NEVER posted (ownership comes from the route).
    expect($form)
        ->not->toContain('name="patient_id"')
        ->not->toContain('name="status"')
        ->not->toContain('name="source"')
        ->not->toContain('name="appointment_type"')
        ->not->toContain('name="confirmed_date"')
        ->not->toContain('name="reviewed_by"');
});

it('omits the time field from delivery cards and keeps it on counseling cards', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();

    $delivery = phase3d3b4ConfirmedAppointment($patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
        'requested_time' => null,
        'confirmed_date' => '2026-11-10',
        'confirmed_time' => null,
    ]);
    $counseling = phase3d3b4ConfirmedAppointment($patient, [
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'confirmed_date' => '2026-11-12',
        'confirmed_time' => '09:00',
    ]);

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    $deliveryForm = phase3d3b4RescheduleFormSegment($content, $delivery);
    $counselingForm = phase3d3b4RescheduleFormSegment($content, $counseling);

    // K5: delivery proposals are date-only - the time input is not rendered.
    expect($deliveryForm)->not->toBe('')
        ->not->toContain('name="proposed_time"')
        ->toContain('name="proposed_date"');

    // Checkup and counseling both require a time inside clinic hours.
    expect($counselingForm)->not->toBe('')
        ->toContain('name="proposed_time"')
        ->toContain('required');
});

it('renders both change forms together on one eligible confirmed card', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($content, 'Request Cancellation'))->toBe(1)
        ->and(substr_count($content, 'Request Reschedule'))->toBe(1)
        ->and(phase3d3b4CancellationFormSegment($content, $appointment))->not->toBe('')
        ->and(phase3d3b4RescheduleFormSegment($content, $appointment))->not->toBe('');
});

/*
|--------------------------------------------------------------------------
| Pending display (type-aware badge) + status stays Confirmed
|--------------------------------------------------------------------------
*/

it('shows the pending reschedule badge with the proposed schedule while the appointment stays confirmed', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);

    phase3d3b4ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:30',
        'reason' => 'Work shift changed, need a later slot.',
    ])->assertSessionHas('success');

    expect($appointment->fresh()->status)->toBe(Appointment::STATUS_CONFIRMED);

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('Reschedule Request Pending')
        ->toContain('November 20, 2026')
        ->toContain('10:30')
        ->toContain('Your reason:')
        ->toContain('Work shift changed, need a later slot.')
        // Status stays Confirmed: no pending-confirmation emphasis, and
        // no forms may be offered while the request awaits review.
        ->not->toContain('Awaiting clinic confirmation')
        ->not->toContain('Cancellation request pending')
        ->not->toContain('Request Reschedule')
        ->not->toContain('Request Cancellation')
        ->not->toContain('Cancel Appointment');
});

it('keeps the cancellation pending wording intact for cancellation-type requests', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);

    phase3d3b4CancellationRequestPost($user, $appointment, [
        'reason' => 'Family constraint, please review.',
    ])->assertSessionHas('success');

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('Cancellation request pending')
        ->toContain('Your cancellation reason:')
        ->toContain('Family constraint, please review.')
        ->not->toContain('Reschedule Request Pending');
});

/*
|--------------------------------------------------------------------------
| Review outcomes: approved banner + declined explanation
|--------------------------------------------------------------------------
*/

it('shows the approved reschedule outcome with the newly confirmed schedule', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);
    $staff = phase3d3b4User('staff');

    phase3d3b4ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:30',
        'reason' => 'Traveling that week.',
    ])->assertSessionHas('success');

    $request = AppointmentChangeRequest::sole();
    phase3d3b4Review($staff, 'appointment-change-requests.approve', $request, [])
        ->assertSessionHas('success');

    $appointment->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-20')
        ->and($appointment->confirmed_time)->toBe('10:30');

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('Your reschedule request was approved.')
        // The NEW confirmed schedule, on the confirmed card.
        ->toContain('November 20, 2026')
        ->toContain('10:30')
        ->not->toContain('Reschedule Request Pending')
        ->not->toContain('Awaiting clinic confirmation');

    // The card is actionable again (no pending request remains).
    expect(substr_count($content, 'Request Reschedule'))->toBe(1);
});

it('shows the declined reschedule outcome with the clinic explanation and original schedule', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);
    $staff = phase3d3b4User('staff');

    phase3d3b4ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:30',
        'reason' => 'Traveling that week.',
    ])->assertSessionHas('success');

    $request = AppointmentChangeRequest::sole();
    phase3d3b4Review($staff, 'appointment-change-requests.decline', $request, [
        'review_note' => 'No available slot that day.',
    ])->assertSessionHas('success');

    $appointment->refresh();

    // The original confirmed appointment is untouched by a decline.
    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED)
        ->and($appointment->confirmed_date?->toDateString())->toBe('2026-11-07')
        ->and($appointment->confirmed_time)->toBe('14:00');

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->toContain('Your reschedule request was declined.')
        ->toContain('No available slot that day.')
        // Original schedule kept and still displayed as confirmed.
        ->toContain('Clinic-confirmed schedule:')
        ->toContain('November 7, 2026')
        ->toContain('14:00')
        ->not->toContain('Reschedule Request Pending');
});

/*
|--------------------------------------------------------------------------
| Isolation: other patients' rows never render
|--------------------------------------------------------------------------
*/

it('never shows another patient appointment, name or schedule', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $mine = phase3d3b4ConfirmedAppointment($patient);

    $foreigner = phase3d3b4Patient(['first_name' => 'Foreigner', 'last_name' => 'Outsider']);
    $theirs = phase3d3b4ConfirmedAppointment($foreigner, [
        'requested_date' => '2026-11-09',
        'requested_time' => '16:45',
        'confirmed_date' => '2026-11-16',
        'confirmed_time' => '16:45',
    ]);

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($content)
        ->not->toContain('Foreigner')
        ->not->toContain('Outsider')
        ->not->toContain('16:45')
        ->not->toContain(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $theirs->id])
        )
        ->toContain(
            route('patient.appointments.rescheduleRequest', ['appointmentId' => $mine->id])
        );
});

/*
|--------------------------------------------------------------------------
| Section grouping + history pagination unchanged
|--------------------------------------------------------------------------
*/

it('keeps the three section headings and history pagination intact', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();

    phase3d3b4PendingAppointment($patient);
    phase3d3b4ConfirmedAppointment($patient);
    foreach (range(1, 11) as $i) {
        phase3d3b4ConfirmedAppointment($patient, [
            'status' => Appointment::STATUS_COMPLETED,
            'confirmed_date' => '2026-09-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            'confirmed_time' => '09:00',
        ]);
    }

    $this->actingAs($user)->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('Pending Requests')
        ->assertSee('Upcoming Confirmed Appointments')
        ->assertSee('Appointment History')
        ->assertSee('of 11 history entries')
        ->assertSee('page=2', false);
});

/*
|--------------------------------------------------------------------------
| Failed resubmission: right disclosure reopened, right form keeps old
| input, cross-form reason leakage prevented, scoped DomainException
|--------------------------------------------------------------------------
*/

it('reopens the reschedule disclosure, preserves old input and keeps the reason out of cancellation forms', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $appointment = phase3d3b4ConfirmedAppointment($patient);
    $reason = 'My detailed reschedule reason text.';

    // Failure 1: invalid proposed date, valid (long) reason - old input
    // must come back on the RIGHT form only.
    phase3d3b4ReschedulePost($user, $appointment, [
        'proposed_date' => '20/11/2026', // invalid format -> validation failure
        'proposed_time' => '10:30',
        'reason' => $reason,
    ])->assertSessionHasErrors('proposed_date');

    expect(AppointmentChangeRequest::count())->toBe(0);

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    // The reschedule disclosure is reopened (open attr on ITS details).
    expect(preg_match(
        '/<details[^>]*\bopen\b[^>]*>\s*<summary[^>]*>\s*Request Reschedule/s',
        $content
    ))->toBe(1);

    // Old input preserved in the reschedule fields.
    $form = phase3d3b4RescheduleFormSegment($content, $appointment);
    expect($form)
        ->toContain('value="20/11/2026"')
        ->toContain('value="10:30"')
        ->toContain($reason);

    // Field error rendered against its input.
    expect($content)
        ->toContain('id="reschedule-date-error-' . $appointment->id . '"');

    // Neither cancellation textarea may echo the reschedule reason, and
    // it appears exactly once on the whole page (reschedule textarea).
    expect(substr_count($content, $reason))->toBe(1)
        ->and(phase3d3b4CancellationFormSegment($content, $appointment))
            ->not->toContain($reason);

    // Failure 2: short reason - the reason error renders exactly ONCE,
    // inside the reschedule form, never inside a cancellation form.
    phase3d3b4ReschedulePost($user, $appointment, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:30',
        'reason' => 'ab',
    ])->assertSessionHasErrors('reason');

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect(substr_count($content, 'must be at least 3 characters'))->toBe(1)
        ->and(substr_count($content, 'Request Reschedule'))->toBe(1);
});

it('surfaces a reschedule service refusal on the targeted card only', function () {
    phase3d3b4Freeze('2026-10-12 09:00:00');
    [$user, $patient] = phase3d3b4Eligible();
    $target = phase3d3b4ConfirmedAppointment($patient);
    $other = phase3d3b4ConfirmedAppointment($patient);

    // First submission succeeds -> one pending change request.
    phase3d3b4ReschedulePost($user, $target, [
        'proposed_date' => '2026-11-20',
        'proposed_time' => '10:00',
    ])->assertSessionHas('success');

    // Second submission (double submit) -> DomainException, keyed
    // 'appointment' + scoped by the flashed errorAppointmentId.
    phase3d3b4ReschedulePost($user, $target, [
        'proposed_date' => '2026-11-21',
        'proposed_time' => '11:00',
    ])->assertSessionHasErrors('appointment');

    expect(AppointmentChangeRequest::count())->toBe(1);

    $content = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    $message = 'This appointment already has a change request awaiting clinic review.';

    // Rendered exactly once - on the TARGETED card, not on every card.
    expect(substr_count($content, $message))->toBe(1)
        ->and(substr_count($content, 'Reschedule Request Pending'))->toBe(1);

    // The targeted card hides its forms (pending request); the unrelated
    // eligible card still offers its own reschedule form.
    expect(phase3d3b4RescheduleFormSegment($content, $target))->toBe('')
        ->and(phase3d3b4RescheduleFormSegment($content, $other))->not->toBe('')
        ->and(substr_count($content, 'Request Reschedule'))->toBe(1);
});
