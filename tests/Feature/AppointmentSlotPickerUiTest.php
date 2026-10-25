<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Phase 4 - Shared 30-Minute Slot Picker UI
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the Blade integration
| of the shared slot-picker component across all four timed booking
| workflows: patient book, patient reschedule, staff confirm and staff
| walk-in. Asserts the component markup (endpoint wiring, field names,
| carrier input, mandated copy, stale-response guard, exact-match-only
| preselection), the delivery date-only rules, removal of the old free-
| form time inputs, the Alpine readiness gating on submit controls, and
| the route/role boundaries the pickers rely on.
|
| JavaScript runtime behavior (fetch, stale-response dropping, retry)
| cannot execute in feature tests; those rules are covered by SOURCE
| assertions on the rendered factory plus the browser UAT checklist in
| docs/IMPLEMENTATION_PROGRESS.md.
*/

function spuPatientUser(): User
{
    return User::factory()->create(['role' => 'patient', 'name' => 'Picker Patient']);
}

function spuStaff(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Picker Staff']);
}

function spuPatient(): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => 'Picker',
        'last_name' => "Patient{$sequence}",
        'age' => 26,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => "spu{$sequence}@picker.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

/** @return array{0: User, 1: Patient} */
function spuPortalPair(): array
{
    $user = spuPatientUser();
    $patient = spuPatient();
    $patient->user_id = $user->id;
    $patient->save();

    return [$user, $patient];
}

function spuAppointment(Patient $patient, array $overrides = []): Appointment
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
    ], $overrides));
}

/**
 * Js::from() renders the picker config inside JSON.parse('...'):
 * quotes become \u0022 and each json_encode-escaped slash (\/) is
 * further backslash-escaped for the single-quoted JS string context,
 * yielding \\\/ in the final HTML. Build exact match needles.
 */
function spuConfigUrlNeedle(string $routeName): string
{
    return str_replace('/', '\\\\\\/', route($routeName));
}

function spuConfigPairNeedle(string $key, string $value): string
{
    return '\u0022' . $key . '\u0022:\u0022' . str_replace('/', '\\\\\\/', $value) . '\u0022';
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 1. Patient booking form
|--------------------------------------------------------------------------
*/

it('renders the shared slot picker on the patient booking form', function () {
    [$user] = spuPortalPair();

    $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee('data-slot-picker', false)
        ->assertSee('data-picker-id="book"', false)
        ->assertSee('data-slot-carrier', false)
        ->assertSee(spuConfigUrlNeedle('patient.appointments.slots'), false)
        ->assertSee('slot_ui_book', false);
});

it('binds requested_time as the booking picker carrier field', function () {
    [$user] = spuPortalPair();

    $html = $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(spuConfigPairNeedle('timeName', 'requested_time'))
        ->and($html)->toContain(spuConfigPairNeedle('dateId', 'requested_date'));
});

it('removes the old free-form requested_time input from the booking form', function () {
    [$user] = spuPortalPair();

    $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertDontSee('id="requested_time"', false)
        ->assertDontSee('name="requested_time"', false);
});

it('gates the booking submit on slot-picker readiness', function () {
    [$user] = spuPortalPair();

    $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee('slot-picker-state', false)
        ->assertSee("slotReady = (\$event.detail.id === 'book'", false)
        ->assertSee('requiresTime && !slotReady', false)
        ->assertSee(':disabled="!ready || (!requiresTime)"', false);
});

it('watches selection and loading changes and deduplicates readiness events', function () {
    [$user] = spuPortalPair();

    $html = $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain("\$watch('selected'")
        ->toContain("\$watch('loading'")
        ->toContain('lastEmittedReady === ready')
        ->toContain('this.lastEmittedReady = ready')
        ->toContain("this.\$dispatch('slot-picker-state'")
        ->toContain('ready: ready');
});

/*
|--------------------------------------------------------------------------
| 2. Patient reschedule form
|--------------------------------------------------------------------------
*/

it('renders the shared slot picker in the reschedule card for a confirmed online checkup', function () {
    [$user, $patient] = spuPortalPair();
    $appointment = spuAppointment($patient);

    $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertSee('data-picker-id="reschedule-' . $appointment->id . '"', false)
        ->assertSee(spuConfigUrlNeedle('patient.appointments.slots'), false)
        ->assertSee(spuConfigPairNeedle('timeName', 'proposed_time'), false)
        ->assertDontSee('id="reschedule-time-' . $appointment->id . '"', false);
});

it('keeps delivery reschedules date-only with no time picker', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-18 09:00', 'Asia/Manila'));

    [$user, $patient] = spuPortalPair();
    $appointment = spuAppointment($patient, [
        'appointment_type' => Appointment::TYPE_DELIVERY_REQUEST,
    ]);

    $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->assertDontSee('data-picker-id="reschedule-' . $appointment->id . '"', false)
        ->assertDontSee('proposed_time', false);
});

it('renders one reschedule picker per confirmed appointment with distinct instance ids', function () {
    [$user, $patient] = spuPortalPair();
    $first = spuAppointment($patient);
    $second = spuAppointment($patient, ['requested_time' => '11:00', 'confirmed_time' => '11:00']);

    $html = $this->actingAs($user)
        ->get(route('patient.appointments.index'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-picker-id="reschedule-' . $first->id . '"')
        ->toContain('data-picker-id="reschedule-' . $second->id . '"');
});

/*
|--------------------------------------------------------------------------
| 3. Staff confirm form (appointments calendar)
|--------------------------------------------------------------------------
*/

it('renders the shared slot picker in the staff confirm form', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('data-picker-id="confirm"', false)
        ->assertSee(spuConfigUrlNeedle('appointments.slots'), false)
        ->assertSee(spuConfigPairNeedle('timeName', 'confirmed_time'), false)
        ->assertSee(spuConfigPairNeedle('dateId', 'confirm-date'), false)
        ->assertSee(':disabled="!ready || (selected?.type === &#039;delivery_request&#039;)"', false)
        ->assertDontSee('id="confirm-time"', false)
        ->assertSee('id="confirm-time-delivery"', false);
});

it('keeps the original bug-fix comment rule: only one confirmed_time carrier submits', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee("document.querySelector('[data-picker-id=\"confirm\"] [data-slot-carrier]')", false)
        ->assertSee('guardConfirmSchedule', false)
        ->assertSee('confirmSlotReady', false);
});

it('preselects the requested time only through the exact-match sync dispatch', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('depla-slot-sync', false)
        ->assertSee("id: 'confirm', date: selected?.requestedDateIso ?? '', time: selected?.requestedTime ?? ''", false)
        ->assertSee("x-on:depla-slot-sync.window", false);
});

it('gates the confirm submit on slot-picker readiness', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee("confirmSlotReady = (\$event.detail.id === 'confirm'", false)
        ->assertSee("selected?.type !== 'delivery_request' && !confirmSlotReady", false);
});

/*
|--------------------------------------------------------------------------
| 4. Staff walk-in form
|--------------------------------------------------------------------------
*/

it('renders the shared slot picker in the staff walk-in modal', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('data-picker-id="walk-in"', false)
        ->assertSee(spuConfigPairNeedle('timeName', 'appointment_time'), false)
        ->assertSee(spuConfigPairNeedle('dateId', 'walkin-date'), false)
        ->assertSee(':disabled="!ready || (walkIn.type === &#039;delivery_request&#039;)"', false)
        ->assertDontSee('id="walkin-time"', false);
});

it('gates the walk-in submit on slot-picker readiness for timed types', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee("walkInSlotReady = (\$event.detail.id === 'walk-in'", false)
        ->assertSee("walkIn.type !== 'delivery_request' && !walkInSlotReady", false)
        ->assertSee("document.querySelector('[data-picker-id=\"walk-in\"] [data-slot-carrier]')", false)
        ->assertSee('Delivery requests are date-only — no time slot is needed.', false);
});

/*
|--------------------------------------------------------------------------
| 5. Component factory: mandated copy and safety rules (source assertions)
|--------------------------------------------------------------------------
*/

it('ships the mandated network-error copy, retry control and stale-response guard', function () {
    [$user] = spuPortalPair();

    $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee('Unable to load appointment availability. Please try again.', false)
        ->assertSee('data-slot-retry', false)
        ->assertSee('requestSeq', false)
        ->assertSee('stale response', false);
});

it('renders the mandated full-day and empty-slot messages', function () {
    [$user] = spuPortalPair();

    $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee('The clinic is unavailable for appointments on the selected date. Please choose another available date.', false)
        ->assertSee('No available time slots remain for this date. Please choose another date.', false)
        ->assertSee('Loading available times', false);
});

it('renders the server-state labels Booked, Past and Clinic Unavailable', function () {
    [$user] = spuPortalPair();

    $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->assertSee("occupied: 'Booked'", false)
        ->assertSee("past: 'Past'", false)
        ->assertSee("unavailable: 'Clinic Unavailable'", false);
});

it('preselects only an exact available slot start and never rounds legacy times', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee("s.start === candidate && s.state === 'available'", false)
        ->assertSee('The patient-requested time is not available. Please choose another available slot below.', false)
        ->assertSee('data-slot-preselect-notice', false)
        ->assertSee("pendingInitial = ''", false);
});

it('contains no client-side slot catalog — the endpoint response is the only source of truth', function () {
    [$user] = spuPortalPair();

    $html = $this->actingAs($user)
        ->get(route('patient.appointments.create'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('function slotPicker(')
        ->toContain('Array.isArray(data.slots)')
        ->not->toContain("start: '08:00'")
        ->not->toContain("start: '08:30'")
        ->not->toContain("start: '16:30'");
});

it('renders the shared factory script exactly once per page even with two pickers', function () {
    $this->actingAs(spuStaff())
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee('function slotPicker(', false);

    $html = $this->actingAs(spuStaff())->get(route('appointments.index'))->getContent();

    expect(substr_count($html, 'function slotPicker('))->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 6. Route / role boundaries the pickers rely on
|--------------------------------------------------------------------------
*/

it('redirects a guest away from the booking form and both slot endpoints', function () {
    $this->get(route('patient.appointments.create'))->assertRedirect(route('login'));
    $this->get(route('patient.appointments.slots', ['date' => '2026-11-05']))->assertRedirect(route('login'));
    $this->get(route('appointments.slots', ['date' => '2026-11-05']))->assertRedirect(route('login'));
});

it('denies patient accounts on the staff calendar and staff slot endpoint', function () {
    [$user] = spuPortalPair();

    $this->actingAs($user)->get(route('appointments.index'))->assertForbidden();
    $this->actingAs($user)->get(route('appointments.slots', ['date' => '2026-11-05']))->assertForbidden();
});

it('serves the staff slot catalog to staff with 18 server-state slots', function () {
    $this->actingAs(spuStaff())
        ->getJson(route('appointments.slots', ['date' => '2026-11-05']))
        ->assertOk()
        ->assertJsonStructure([
            'date',
            'clinic_today',
            'fully_unavailable',
            'slots' => [['start', 'end', 'label', 'state']],
        ])
        ->assertJsonCount(18, 'slots');
});
