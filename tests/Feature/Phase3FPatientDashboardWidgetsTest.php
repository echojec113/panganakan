<?php

use App\Http\Controllers\PatientPortalController;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PHPUnit\Framework\Assert;

/*
|--------------------------------------------------------------------------
| Phase 3F — Patient Portal Dashboard Widgets (Next Appointment + Maternity Tracker)
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers: patient ownership of
| both new presentation arrays, the confirmed-appointment selection rules
| (effective date fallback, Manila-today filter, time ordering with missing
| times last, id tie-break), exclusion of pending/other-status/soft-deleted
| and foreign appointments, the maternity tracker's safe states (missing
| LMP/EDD, due today, future/past EDD without delivery inference, delivered
| completion state), field allow-lists (no provider/reviewer/risk/ML/
| referral/staff/progress keys), and the preserved access matrix (guest,
| unlinked, archived, flagged) through the unchanged dashboard route.
|
| Data-shape assertions call the controller directly with an authenticated
| request (the dashboard Blade intentionally does not render the arrays
| until Phase 3G); access assertions go through the real HTTP route.
| Frozen-time tests use Carbon::setTestNow in Asia/Manila and are always
| cleared in afterEach so they cannot leak into the rest of the suite.
*/

function phase3fUser(string $role = 'patient', bool $flagged = false): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($flagged) {
        $user->forceFill(['must_change_password' => true])->save();
    }

    return $user;
}

function phase3fPatient(array $overrides = []): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'middle_name' => 'Cruz',
        'age' => 28,
        'email' => "phase3f{$sequence}@portal.test",
        'contact_number' => '09171234567',
        'address_line' => '123 Mabini Street',
        'barangay' => 'Sampaloc',
        'city_municipality' => 'Manila',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3fLink(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/** @return array{0: User, 1: Patient} */
function phase3fEligible(array $patientOverrides = []): array
{
    $user = phase3fUser('patient');
    $patient = phase3fPatient($patientOverrides);
    phase3fLink($patient, $user);

    return [$user, $patient];
}

function phase3fAppointment(Patient $patient, array $overrides = []): Appointment
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

/** A Manila calendar date, offset from the frozen clock (or now). */
function phase3fManilaDate(int $daysAhead = 0): string
{
    return Carbon::now('Asia/Manila')->startOfDay()->addDays($daysAhead)->toDateString();
}

/** Freeze the clock at a Manila wall time. Cleared in afterEach. */
function phase3fFreeze(string $manilaMoment): void
{
    Carbon::setTestNow(Carbon::parse($manilaMoment, 'Asia/Manila'));
}

/**
 * Invoke the unchanged dashboard action directly with an authenticated
 * (or query-string-carrying) request and return the view data, so the two
 * new presentation arrays can be asserted before Phase 3G renders them.
 */
function phase3fDashboardData(User $user, string $queryString = ''): array
{
    $request = Request::create('/patient-portal' . $queryString, 'GET');
    $request->setUserResolver(fn () => $user);

    $view = app(PatientPortalController::class)->dashboard($request);

    Assert::assertInstanceOf(View::class, $view);

    return $view->getData();
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Ownership and access
|--------------------------------------------------------------------------
*/

it('resolves both widget arrays only through the authenticated patient link', function () {
    [$user, $patient] = phase3fEligible(['lmp' => phase3fManilaDate(-140), 'edd' => phase3fManilaDate(140)]);

    $data = phase3fDashboardData($user);

    expect($data['patient']->is($patient))->toBeTrue()
        ->and($data['maternityTracker']['status_label'])->toBe('Ongoing')
        ->and($data)->toHaveKeys(['patient', 'nextAppointment', 'maternityTracker']);
});

it('never returns another patient appointment in the next-appointment array', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user] = phase3fEligible();
    $other = phase3fPatient();
    phase3fAppointment($other, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(1),
        'confirmed_date' => phase3fManilaDate(1),
        'confirmed_time' => '08:00',
    ]);

    $data = phase3fDashboardData($user);

    expect($data['nextAppointment'])->toBeNull();
});

it('ignores a query-string patient id and keeps the own linked record', function () {
    [$user, $patient] = phase3fEligible();
    $other = phase3fPatient(['first_name' => 'Foreigner']);

    $data = phase3fDashboardData($user, '?patient_id=' . $other->id);

    expect($data['patient']->is($patient))->toBeTrue()
        ->and($data['patient']->first_name)->toBe('Maria')
        ->and($data['maternityTracker']['status_label'])->not->toBe('Status not recorded');

    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon') . '?patient_id=' . $other->id)
        ->assertOk()
        ->assertSee('Welcome, Maria!')
        ->assertDontSee('Foreigner');
});

it('keeps guest unlinked archived and flagged access behaviour unchanged', function () {
    $this->get(route('patient.portal.coming-soon'))->assertRedirect(route('login'));

    $unlinkedUser = phase3fUser('patient');
    $this->actingAs($unlinkedUser)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    [$linkedUser, $archived] = phase3fEligible();
    $archived->delete();
    $this->actingAs($linkedUser)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden()
        ->assertSee('Your patient account is currently unavailable');

    $flaggedUser = phase3fUser('patient', true);
    $flaggedPatient = phase3fPatient();
    phase3fLink($flaggedPatient, $flaggedUser);
    $this->actingAs($flaggedUser)
        ->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

/*
|--------------------------------------------------------------------------
| Next appointment
|--------------------------------------------------------------------------
*/

it('selects the earliest eligible confirmed appointment', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(10),
        'confirmed_date' => phase3fManilaDate(10),
        'confirmed_time' => '09:00',
    ]);
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => phase3fManilaDate(3),
        'confirmed_date' => phase3fManilaDate(3),
        'confirmed_time' => '14:00',
    ]);

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect($next['appointment_type_label'])->toBe('Counseling')
        ->and($next['effective_date_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(3))->format('l, F j, Y'))
        ->and($next['effective_time_display'])->toBe('14:00')
        ->and($next['status_label'])->toBe('Confirmed');
});

it('treats a confirmed appointment scheduled for clinic today as eligible', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(0),
        'confirmed_date' => phase3fManilaDate(0),
        'confirmed_time' => '15:30',
    ]);

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect($next)->not->toBeNull()
        ->and($next['effective_date_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(0))->format('l, F j, Y'))
        ->and($next['effective_time_display'])->toBe('15:30');
});

it('excludes pending cancelled declined completed and soft-deleted appointments', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();

    foreach ([
        Appointment::STATUS_PENDING,
        Appointment::STATUS_CANCELLED,
        Appointment::STATUS_DECLINED,
        Appointment::STATUS_COMPLETED,
    ] as $status) {
        phase3fAppointment($patient, [
            'status' => $status,
            'requested_date' => phase3fManilaDate(2),
            'confirmed_date' => phase3fManilaDate(2),
            'confirmed_time' => '08:00',
        ]);
    }

    expect(phase3fDashboardData($user)['nextAppointment'])->toBeNull();

    $softDeleted = phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(4),
        'confirmed_date' => phase3fManilaDate(4),
        'confirmed_time' => '08:00',
    ]);
    $softDeleted->delete();

    expect(phase3fDashboardData($user)['nextAppointment'])->toBeNull();
});

it('falls back to the requested date only when the confirmed date is missing', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();

    // Confirmed date present but PAST while the requested date is future:
    // the effective date is the confirmed one, so this must NOT appear.
    $pastConfirmed = phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(5),
        'confirmed_date' => phase3fManilaDate(-1),
        'confirmed_time' => '09:00',
    ]);

    expect(phase3fDashboardData($user)['nextAppointment'])->toBeNull();

    $pastConfirmed->delete();

    // Confirmed date NULL with a future requested date: eligible via the
    // fallback, displayed on the requested date.
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(3),
        'confirmed_date' => null,
        'confirmed_time' => '11:00',
    ]);

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect($next)->not->toBeNull()
        ->and($next['effective_date_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(3))->format('l, F j, Y'))
        ->and($next['effective_time_display'])->toBe('11:00');
});

it('orders by effective date rather than the requested date', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();

    // Confirmed further out than its requested date.
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(1),
        'confirmed_date' => phase3fManilaDate(10),
        'confirmed_time' => '09:00',
    ]);
    // Confirmed sooner than its requested date.
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(20),
        'confirmed_date' => phase3fManilaDate(5),
        'confirmed_time' => '09:00',
    ]);

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect($next['effective_date_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(5))->format('l, F j, Y'));
});

it('orders by effective time and places missing times last', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();

    // Same date: a later clock time, a missing time, and the earliest time.
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(2),
        'confirmed_date' => phase3fManilaDate(2),
        'confirmed_time' => '14:00',
    ]);
    $dateOnly = phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(2),
        'confirmed_date' => phase3fManilaDate(2),
        'confirmed_time' => null,
        'requested_time' => null,
    ]);
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(2),
        'confirmed_date' => phase3fManilaDate(2),
        'confirmed_time' => '09:00',
    ]);

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect($next['effective_time_display'])->toBe('09:00');

    // Remove the 09:00 row: the date-only row must come before nothing but
    // still lose to any timed row — here only it and the 14:00 remain.
    $patient->appointments()
        ->where('status', Appointment::STATUS_CONFIRMED)
        ->whereNotNull('confirmed_time')
        ->whereDate('confirmed_date', phase3fManilaDate(2))
        ->delete();

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect($next['effective_time_display'])->toBe('Time not recorded')
        ->and($dateOnly->refresh()->deleted_at)->toBeNull();
});

it('breaks same-schedule ties deterministically by appointment id', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();

    // Identical effective schedule; the type label reveals which row won.
    $first = phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'requested_date' => phase3fManilaDate(2),
        'confirmed_date' => phase3fManilaDate(2),
        'confirmed_time' => '09:00',
    ]);
    $second = phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'appointment_type' => Appointment::TYPE_COUNSELING,
        'requested_date' => phase3fManilaDate(2),
        'confirmed_date' => phase3fManilaDate(2),
        'confirmed_time' => '09:00',
    ]);

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect($second->id)->toBeGreaterThan($first->id)
        ->and($next['appointment_type_label'])->toBe('Checkup');

    $first->delete();

    expect(phase3fDashboardData($user)['nextAppointment']['appointment_type_label'])
        ->toBe('Counseling');
});

it('returns a null next appointment when nothing is eligible', function () {
    [$user] = phase3fEligible();

    expect(phase3fDashboardData($user)['nextAppointment'])->toBeNull();
});

it('exposes no provider reviewer or internal appointment metadata', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user, $patient] = phase3fEligible();
    phase3fAppointment($patient, [
        'status' => Appointment::STATUS_CONFIRMED,
        'requested_date' => phase3fManilaDate(2),
        'confirmed_date' => phase3fManilaDate(2),
        'confirmed_time' => '09:00',
        'decline_reason' => 'secret clinic reason',
        'cancellation_reason' => 'secret patient reason',
    ]);

    $next = phase3fDashboardData($user)['nextAppointment'];

    expect(array_keys($next))->toBe([
        'appointment_type_label',
        'effective_date_display',
        'effective_time_display',
        'status_label',
    ]);

    foreach ([
        'patient_id',
        'source',
        'reviewed_by',
        'reviewer',
        'doctor',
        'physician',
        'provider',
        'decline_reason',
        'cancellation_reason',
        'notes',
        'created_at',
    ] as $forbidden) {
        expect(array_key_exists($forbidden, $next))->toBeFalse();
    }

    expect(json_encode($next))->not->toContain('secret');
});

/*
|--------------------------------------------------------------------------
| Maternity tracker
|--------------------------------------------------------------------------
*/

it('returns safe ongoing tracker fields for a valid pregnancy', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user] = phase3fEligible([
        'lmp' => phase3fManilaDate(-100),
        'edd' => phase3fManilaDate(180),
    ]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['is_delivered'])->toBeFalse()
        ->and($tracker['status_label'])->toBe('Ongoing')
        ->and($tracker['lmp_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(-100))->format('M d, Y'))
        ->and($tracker['edd_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(180))->format('M d, Y'))
        ->and($tracker['weeks_along_display'])->toBe('14.3 weeks')
        ->and($tracker['trimester_label'])->toBe('2nd Trimester')
        ->and($tracker['edd_countdown_display'])->toBe('180 days to go')
        ->and($tracker['delivery_date_display'])->toBeNull();
});

it('handles a missing LMP without inventing gestational age', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user] = phase3fEligible(['lmp' => null, 'edd' => phase3fManilaDate(60)]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['lmp_display'])->toBeNull()
        ->and($tracker['weeks_along_display'])->toBeNull()
        ->and($tracker['trimester_label'])->toBeNull()
        ->and($tracker['edd_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(60))->format('M d, Y'))
        ->and($tracker['edd_countdown_display'])->toBe('60 days to go');
});

it('handles a missing EDD without inventing a due date', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user] = phase3fEligible(['lmp' => phase3fManilaDate(-70), 'edd' => null]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['edd_display'])->toBeNull()
        ->and($tracker['edd_countdown_display'])->toBe('Due date not recorded')
        ->and($tracker['weeks_along_display'])->toBe('10.0 weeks')
        ->and($tracker['trimester_label'])->toBe('1st Trimester');
});

it('handles a pregnancy with neither LMP nor EDD recorded', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user] = phase3fEligible(['lmp' => null, 'edd' => null]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['lmp_display'])->toBeNull()
        ->and($tracker['edd_display'])->toBeNull()
        ->and($tracker['weeks_along_display'])->toBeNull()
        ->and($tracker['trimester_label'])->toBeNull()
        ->and($tracker['edd_countdown_display'])->toBe('Due date not recorded')
        ->and($tracker['status_label'])->toBe('Ongoing');
});

it('reports the due date as today when the EDD falls on the clinic date', function () {
    phase3fFreeze('2026-10-14 23:30:00');

    [$user] = phase3fEligible(['lmp' => phase3fManilaDate(-280), 'edd' => phase3fManilaDate(0)]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['edd_countdown_display'])->toBe('Due date is today')
        ->and($tracker['is_delivered'])->toBeFalse()
        ->and($tracker['status_label'])->toBe('Ongoing');
});

it('reports a passed EDD as contact-the-clinic without marking delivery', function () {
    phase3fFreeze('2026-10-14 09:00:00');

    [$user] = phase3fEligible(['lmp' => phase3fManilaDate(-290), 'edd' => phase3fManilaDate(-10)]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['edd_countdown_display'])
        ->toBe('Estimated due date has passed — please contact the clinic')
        ->and($tracker['is_delivered'])->toBeFalse()
        ->and($tracker['status_label'])->toBe('Ongoing')
        ->and($tracker['delivery_date_display'])->toBeNull();
});

it('returns a completion state for a delivered pregnancy', function () {
    [$user] = phase3fEligible([
        'status' => 'DELIVERED',
        'lmp' => phase3fManilaDate(-300),
        'edd' => phase3fManilaDate(-20),
        'delivery_date' => phase3fManilaDate(-22),
    ]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['is_delivered'])->toBeTrue()
        ->and($tracker['status_label'])->toBe('Delivered')
        ->and($tracker['delivery_date_display'])
        ->toBe(Carbon::parse(phase3fManilaDate(-22))->format('M d, Y'))
        ->and($tracker['weeks_along_display'])->toBeNull()
        ->and($tracker['trimester_label'])->toBeNull()
        ->and($tracker['edd_countdown_display'])->toBeNull();
});

it('handles a delivered pregnancy with no recorded delivery date', function () {
    [$user] = phase3fEligible([
        'status' => 'DELIVERED',
        'lmp' => phase3fManilaDate(-300),
        'edd' => phase3fManilaDate(-20),
        'delivery_date' => null,
    ]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect($tracker['is_delivered'])->toBeTrue()
        ->and($tracker['status_label'])->toBe('Delivered')
        ->and($tracker['delivery_date_display'])->toBeNull()
        ->and($tracker['edd_countdown_display'])->toBeNull();
});

it('exposes no risk ML referral staff or progress fields in the tracker', function () {
    [$user] = phase3fEligible(['lmp' => phase3fManilaDate(-100), 'edd' => phase3fManilaDate(180)]);

    $tracker = phase3fDashboardData($user)['maternityTracker'];

    expect(array_keys($tracker))->toBe([
        'is_delivered',
        'status_label',
        'lmp_display',
        'edd_display',
        'weeks_along_display',
        'trimester_label',
        'edd_countdown_display',
        'delivery_date_display',
    ]);

    $serialized = json_encode($tracker);

    foreach ([
        'risk',
        'Risk',
        'ml_',
        'prediction',
        'referral',
        'notes',
        'factor',
        'evidence',
        'percent',
        'progress',
        'reviewed_by',
        'confirmed_by',
        'confirmation_source',
        'follow_up',
    ] as $forbidden) {
        expect($serialized)->not->toContain($forbidden);
    }
});
