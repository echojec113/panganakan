<?php

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicAvailabilityService;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Phase 2 - Patient & Staff slot availability endpoints
|--------------------------------------------------------------------------
| SQLite :memory: only. Covers authorization (guest / clinic-role denial
| / patient access), the JSON schema (date, clinic_today,
| fully_unavailable and exactly 18 slots with start/end/label/state),
| occupied/past/unavailable states, the 422 contract for an invalid
| date, and the patient-safety rule: the payload NEVER carries patient
| names, ids, appointment ids, notes or closure reasons.
*/

function asePatientUser(): User
{
    return User::factory()->create(['role' => 'patient', 'name' => 'Slots Patient']);
}

function asePatient(): Patient
{
    static $sequence = 0;
    $sequence++;

    return Patient::create([
        'first_name' => 'Slots',
        'last_name' => "Patient{$sequence}",
        'age' => 26,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => "ase{$sequence}@slots.test",
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function aseConfirmedAppointment(array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => asePatient()->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '10:00',
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '10:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ], $overrides));
}

function asePortalPair(): array
{
    $user = asePatientUser();
    $patient = asePatient();
    $patient->user_id = $user->id;
    $patient->save();

    return [$user, $patient];
}

function aseSlotStates(array $payload): array
{
    $states = [];

    foreach ($payload['slots'] as $slot) {
        $states[$slot['start']] = $slot['state'];
    }

    return $states;
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| Authorization
|--------------------------------------------------------------------------
*/

it('redirects a guest to login on both slot endpoints', function () {
    $this->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertRedirect(route('login'));

    $this->get(route('appointments.slots', ['date' => '2026-11-05']))
        ->assertRedirect(route('login'));
});

it('lets a linked patient read their slot catalog', function () {
    [$user] = asePortalPair();

    $this->actingAs($user)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertOk()
        ->assertJsonStructure([
            'date',
            'clinic_today',
            'fully_unavailable',
            'slots' => [['start', 'end', 'label', 'state']],
        ]);
});

it('refuses clinic roles on the patient slot endpoint with 403', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertForbidden();
});

it('lets staff and admin read the staff slot catalog', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($staff)
        ->get(route('appointments.slots', ['date' => '2026-11-05']))
        ->assertOk()
        ->assertJsonStructure([
            'date',
            'clinic_today',
            'fully_unavailable',
            'slots' => [['start', 'end', 'label', 'state']],
        ]);

    $this->actingAs($admin)
        ->get(route('appointments.slots', ['date' => '2026-11-05']))
        ->assertOk();
});

it('refuses a patient account on the staff slot endpoint', function () {
    [$user] = asePortalPair();

    $this->actingAs($user)
        ->get(route('appointments.slots', ['date' => '2026-11-05']))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Payload contract
|--------------------------------------------------------------------------
*/

it('returns exactly 18 grid slots in canonical order with exclusive-end labels', function () {
    [$user] = asePortalPair();

    $response = $this->actingAs($user)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertOk();

    $payload = $response->json();

    expect($payload['date'])->toBe('2026-11-05')
        ->and($payload['clinic_today'])->toBeFalse()
        ->and($payload['fully_unavailable'])->toBeFalse()
        ->and($payload['slots'])->toHaveCount(18)
        ->and($payload['slots'][0]['start'])->toBe('08:00')
        ->and($payload['slots'][0]['end'])->toBe('08:30')
        ->and($payload['slots'][17]['start'])->toBe('16:30')
        ->and($payload['slots'][17]['end'])->toBe('17:00');

    foreach ($payload['slots'] as $slot) {
        expect($slot['state'])->toBeIn([
            ClinicAvailabilityService::STATE_AVAILABLE,
            ClinicAvailabilityService::STATE_OCCUPIED,
            ClinicAvailabilityService::STATE_PAST,
            ClinicAvailabilityService::STATE_UNAVAILABLE,
        ]);
    }
});

it('marks the 10:00 slot occupied when a confirmed appointment holds it', function () {
    [$user] = asePortalPair();
    aseConfirmedAppointment();

    $payload = $this->actingAs($user)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertOk()
        ->json();

    $states = aseSlotStates($payload);

    expect($states['10:00'])->toBe(ClinicAvailabilityService::STATE_OCCUPIED)
        ->and($states['09:00'])->toBe(ClinicAvailabilityService::STATE_AVAILABLE);
});

it('marks a partially closed window unavailable and leaves the rest of the day available', function () {
    [$user] = asePortalPair();

    app(ClinicAvailabilityService::class)->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '12:00',
        'end_time' => '13:00',
        'reason' => 'Internal lunch window.',
    ], User::factory()->create(['role' => 'admin']));

    $payload = $this->actingAs($user)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertOk()
        ->json();

    $states = aseSlotStates($payload);

    expect($states['12:00'])->toBe(ClinicAvailabilityService::STATE_UNAVAILABLE)
        ->and($states['12:30'])->toBe(ClinicAvailabilityService::STATE_UNAVAILABLE)
        ->and($states['11:30'])->toBe(ClinicAvailabilityService::STATE_AVAILABLE)
        ->and($states['13:00'])->toBe(ClinicAvailabilityService::STATE_AVAILABLE);
});

it('flags fully unavailable dates without leaking the internal reason', function () {
    [$user] = asePortalPair();

    app(ClinicAvailabilityService::class)->createClosure([
        'start_date' => '2026-11-20',
        'end_date' => '2026-11-20',
        'all_day' => true,
        'reason' => 'CONFIDENTIAL board meeting.',
    ], User::factory()->create(['role' => 'admin']));

    $response = $this->actingAs($user)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-20']))
        ->assertOk();

    $payload = $response->json();

    expect($payload['fully_unavailable'])->toBeTrue();

    foreach ($payload['slots'] as $slot) {
        expect($slot['state'])->toBe(ClinicAvailabilityService::STATE_UNAVAILABLE);
    }

    $response->assertDontSee('CONFIDENTIAL');
    $response->assertDontSee('board meeting');
});

it('marks past dates entirely past', function () {
    [$user] = asePortalPair();
    Carbon::setTestNow(Carbon::parse('2026-11-10 12:00', 'Asia/Manila'));

    $payload = $this->actingAs($user)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertOk()
        ->json();

    $states = aseSlotStates($payload);

    foreach ($states as $state) {
        expect($state)->toBe(ClinicAvailabilityService::STATE_PAST);
    }
});

it('never exposes patient identity, appointment ids or notes in the slot payload', function () {
    [$user, $patient] = asePortalPair();
    aseConfirmedAppointment([
        'patient_id' => $patient->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('patient.appointments.slots', ['date' => '2026-11-05']))
        ->assertOk();

    $payload = $response->json();

    // Top-level and slot shapes are fixed: nothing else can ride along.
    expect(array_keys($payload))->toBe(['date', 'clinic_today', 'fully_unavailable', 'slots']);

    foreach ($payload['slots'] as $slot) {
        expect(array_keys($slot))->toBe(['start', 'end', 'label', 'state']);
    }

    $raw = $response->getContent();
    expect($raw)->not->toContain($patient->first_name)
        ->and($raw)->not->toContain($patient->last_name)
        ->and($raw)->not->toContain('patient_id')
        ->and($raw)->not->toContain('appointment_id');
});

/*
|--------------------------------------------------------------------------
| 422 contract
|--------------------------------------------------------------------------
*/

it('returns 422 JSON for a malformed date on both endpoints', function () {
    [$user] = asePortalPair();
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->getJson(route('patient.appointments.slots', ['date' => 'not-a-date']))
        ->assertStatus(422)
        ->assertJsonStructure(['message']);

    $this->actingAs($staff)
        ->getJson(route('appointments.slots', ['date' => '2026-13-45']))
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});

it('returns 422 when the date query parameter is missing', function () {
    [$user] = asePortalPair();

    $this->actingAs($user)
        ->getJson(route('patient.appointments.slots'))
        ->assertStatus(422)
        ->assertJsonStructure(['message']);
});
