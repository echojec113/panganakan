<?php

use App\Models\AuditLog;
use App\Models\Baby;
use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Services\PregnancyOutcomeRecordingService;
use App\Support\PregnancyOutcomeVocabulary;
use Illuminate\Database\Eloquent\Model;

function pregnancyOutcomeRecordingUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function pregnancyOutcomeRecordingPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Reyes',
        'birthdate' => '1996-05-01',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function pregnancyOutcomeRecordingPayload(array $overrides = []): array
{
    return array_merge([
        'delivery_date' => '2026-08-05',
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        'outcome_notes' => 'Confirmed delivery at the clinic.',
        'babies' => [
            [
                'first_name' => 'Baby',
                'last_name' => 'Reyes',
                'sex' => 'Female',
                'date_of_birth' => '2026-08-05',
                'time_of_birth' => '09:30',
                'birth_weight' => '2.80',
                'birth_length' => '48',
            ],
        ],
    ], $overrides);
}

function pregnancyOutcomeRecordingService(): PregnancyOutcomeRecordingService
{
    return app(PregnancyOutcomeRecordingService::class);
}

function pregnancyOutcomeRecordingBaby(string $dob, string $timeOfBirth = '09:30'): array
{
    return [
        'first_name' => 'Baby',
        'last_name' => 'Reyes',
        'date_of_birth' => $dob,
        'time_of_birth' => $timeOfBirth,
    ];
}

/**
 * Asserts that a rejected/incomplete service or controller call left the
 * patient and every related row completely untouched.
 */
function pregnancyOutcomeRecordingAssertNoPartialWrite(Patient $patient): void
{
    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
    expect($patient->delivery_date)->toBeNull();
    expect($patient->para)->toBe(0);
    expect(Baby::where('patient_id', $patient->id)->count())->toBe(0);
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
}

it('A B E F G H J K L N O — records a confirmed THIS_CLINIC delivery end to end', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('patients.delivered'));

    $patient->refresh();
    expect($patient->status)->toBe('DELIVERED');            // E
    expect($patient->delivery_date->toDateString())->toBe('2026-08-05'); // F
    expect($patient->para)->toBe(1);                        // G

    $baby = $patient->babies()->first();
    expect($patient->babies()->count())->toBe(1);           // H
    expect($baby->first_name)->toBe('Baby');
    expect($baby->last_name)->toBe('Reyes');
    expect($baby->sex)->toBe('Female');
    expect($baby->date_of_birth->toDateString())->toBe('2026-08-05');
    expect($baby->time_of_birth->format('H:i'))->toBe('09:30');
    expect((string) $baby->birth_weight)->toBe('2.80');
    expect((string) $baby->birth_length)->toBe('48.0');

    $outcome = $patient->pregnancyOutcome;
    expect($outcome)->not->toBeNull();                      // J
    expect($outcome->outcome_type)->toBe(PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED);
    expect($outcome->delivery_location)->toBe('THIS_CLINIC'); // K
    expect($outcome->confirmation_source)->toBe('CLINIC_RECORD'); // L
    expect($outcome->confirmed_at)->not->toBeNull();        // M
    expect($outcome->confirmed_by)->toBe($user->id);        // N
    expect($outcome->notes)->toBe('Confirmed delivery at the clinic.'); // O
    expect($outcome->hasConfirmedOutcome())->toBeTrue();

    // Audit log recorded only after the transaction succeeded.
    $audit = AuditLog::where('module', 'PATIENT')->orderByDesc('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->description)->toContain('Recorded confirmed delivery outcome');
});

it('B — records an ANOTHER_FACILITY delivery from an other-facility report', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload([
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_ANOTHER_FACILITY,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_OTHER_FACILITY_REPORT,
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);
    $response->assertSessionHasNoErrors();

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->delivery_location)->toBe('ANOTHER_FACILITY');
    expect($outcome->confirmation_source)->toBe('OTHER_FACILITY_REPORT');
});

it('C — records a HOME delivery confirmed via patient report', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload([
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_HOME,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_PATIENT_REPORT,
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);
    $response->assertSessionHasNoErrors();

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->delivery_location)->toBe('HOME');
    expect($outcome->confirmation_source)->toBe('PATIENT_REPORT');
});

it('D — records an OTHER delivery with OTHER source', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload([
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_OTHER,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_OTHER,
        'outcome_notes' => '  Delivery occurred while travelling.  ',
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);
    $response->assertSessionHasNoErrors();

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->delivery_location)->toBe('OTHER');
    expect($outcome->confirmation_source)->toBe('OTHER');
    expect($outcome->notes)->toBe('Delivery occurred while travelling.');
});

it('I — supports multiple babies and increments para exactly once', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload([
        'babies' => [
            ['first_name' => 'Baby One', 'date_of_birth' => '2026-08-05', 'time_of_birth' => '09:30'],
            ['first_name' => 'Baby Two', 'date_of_birth' => '2026-08-05', 'time_of_birth' => '09:31'],
        ],
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);
    $response->assertSessionHasNoErrors();

    $patient->refresh();
    expect($patient->babies()->count())->toBe(2);
    expect($patient->para)->toBe(1);
});

it('M — stamps the confirmation time with the server clock, not the request', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload(['confirmed_at' => '2020-01-01 00:00:00']);

    $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->confirmed_at->toDateString())->toBe(now()->toDateString());
    expect($outcome->confirmed_at->toDateString())->not->toBe('2020-01-01');
});

it('N — confirmed_by is the acting staff member, ignoring forged ids', function () {
    $user = pregnancyOutcomeRecordingUser();
    $otherUser = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload(['confirmed_by' => $otherUser->id]);

    $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);

    expect($patient->refresh()->pregnancyOutcome->confirmed_by)->toBe($user->id);
});

it('P — updates a blank placeholder outcome row in place and clears follow-up', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => now(),
        'follow_up_recorded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $response->assertSessionHasNoErrors();

    expect(PregnancyOutcome::where('patient_id', $patient->id)->count())->toBe(1);

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->follow_up_status)->toBeNull();
    expect($outcome->follow_up_recorded_at)->toBeNull();
    expect($outcome->follow_up_recorded_by)->toBeNull();
    expect($outcome->outcome_type)->toBe('DELIVERED');
});

it('Q R S T — a repeated/double submission fails safely and records nothing twice', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $first = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $first->assertSessionHasNoErrors();

    $patient->refresh();
    expect($patient->para)->toBe(1);

    $second = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $second->assertSessionHasErrors('status');

    $patient->refresh();
    expect($patient->para)->toBe(1);                          // R — not incremented again
    expect($patient->babies()->count())->toBe(1);             // S — no duplicate babies
    expect(PregnancyOutcome::where('patient_id', $patient->id)->count())->toBe(1); // T — one row only
});

it('U — a legacy REFERRED pregnancy is rejected and left untouched', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient(['status' => 'REFERRED']);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $response->assertSessionHasErrors('status');

    $patient->refresh();
    expect($patient->status)->toBe('REFERRED');
    expect($patient->delivery_date)->toBeNull();
    expect($patient->para)->toBe(0);
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('V — an already-DELIVERED pregnancy is rejected and untouched', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-06-01', 'para' => 1]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $response->assertSessionHasErrors('status');

    $patient->refresh();
    expect($patient->status)->toBe('DELIVERED');
    expect($patient->delivery_date->toDateString())->toBe('2026-06-01');
    expect($patient->para)->toBe(1);
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('W — rejects an invalid delivery location', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload([
        'delivery_location' => 'CLINIC_2',
    ]));

    $response->assertSessionHasErrors('delivery_location');
    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
});

it('X — rejects an invalid confirmation source', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload([
        'confirmation_source' => 'HEARSAY',
    ]));

    $response->assertSessionHasErrors('confirmation_source');
    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
});

it('Y — cannot forge outcome_type', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload(['outcome_type' => 'STILLBIRTH']);

    $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);

    expect($patient->refresh()->pregnancyOutcome->outcome_type)->toBe('DELIVERED');
});

it('Z — cannot forge confirmed_at', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload(['confirmed_at' => '2020-01-01 00:00:00']);

    $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);

    expect($patient->refresh()->pregnancyOutcome->confirmed_at->toDateString())->toBe(now()->toDateString());
});

it('AB — each baby date_of_birth must equal the submitted delivery date', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload([
        'delivery_date' => '2026-08-05',
        'babies' => [
            ['first_name' => 'Baby', 'date_of_birth' => '2026-08-06', 'time_of_birth' => '09:30'],
        ],
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);

    $response->assertSessionHasErrors('babies.0.date_of_birth');
    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
    expect($patient->babies()->count())->toBe(0);
});

it('AC — rejects a future baby date of birth', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $today = now()->toDateString();
    $tomorrow = now()->addDay()->toDateString();

    $payload = pregnancyOutcomeRecordingPayload([
        'delivery_date' => $today,
        'babies' => [[
            'date_of_birth' => $tomorrow,
            'time_of_birth' => '09:30',
        ]],
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);

    $response->assertSessionHasErrors('babies.0.date_of_birth');
    expect($patient->refresh()->babies()->count())->toBe(0);
});

it('AD — at least one baby is required', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $payload = pregnancyOutcomeRecordingPayload(['babies' => []]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), $payload);

    $response->assertSessionHasErrors('babies');
});

it('AE — a failed transaction leaves no partial state behind', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    try {
        Baby::creating(function () {
            throw new \RuntimeException('Simulated baby persistence failure');
        });

        expect(fn () => app(PregnancyOutcomeRecordingService::class)->recordConfirmedDelivery(
            $patient,
            $user,
            '2026-08-05',
            PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
            PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
            [[
                'first_name' => 'Baby',
                'last_name' => 'Reyes',
                'date_of_birth' => '2026-08-05',
                'time_of_birth' => '09:30',
            ]],
            null
        ))->toThrow(\RuntimeException::class);
    } finally {
        Baby::flushEventListeners();
    }

    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
    expect($patient->delivery_date)->toBeNull();
    expect($patient->para)->toBe(0);
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
    expect(Baby::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AF — a legacy DELIVERED patient without an outcome record stays valid', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-06-01']);

    expect($patient->pregnancyOutcome)->toBeNull();
    expect(PregnancyOutcome::all()->count())->toBe(0);

    $history = $this->actingAs($user)->get(route('patients.delivered.history', $patient->id));
    $history->assertOk();
});

it('AG AH AI — Start New Pregnancy still works after a new confirmed delivery and inherits nothing', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient(['gravida' => 2, 'para' => 1]);

    $deliver = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $deliver->assertSessionHasNoErrors();

    // pregnancy is closed, para carries the delivery increment
    $patient->refresh();
    expect($patient->para)->toBe(2);

    $response = $this->actingAs($user)->post(route('patients.start-new-pregnancy', $patient->id), [
        'lmp' => '2025-11-01',
        'edd' => '2026-08-08',
        'address' => 'New address',
        'contact_number' => '09181234567',
    ]);
    $response->assertSessionHasNoErrors();

    $newPregnancy = Patient::where('status', 'ONGOING')->where('first_name', 'Maria')->firstOrFail();

    // AV — started from a delivered pregnancy
    expect($newPregnancy->id)->not->toBe($patient->id);
    expect($newPregnancy->gravida)->toBe(3);
    expect($newPregnancy->para)->toBe(2);
    expect($newPregnancy->status)->toBe('ONGOING');
    expect($newPregnancy->delivery_date)->toBeNull();
    expect($newPregnancy->pregnancyOutcome)->toBeNull();          // AH — no inherited outcome
    expect($newPregnancy->babies()->count())->toBe(0);            // AI — no inherited babies
});

it('AJ — updateBaby backend rejects mutation for a DELIVERED pregnancy', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-06-01']);
    $baby = Baby::create([
        'patient_id' => $patient->id,
        'first_name' => 'Baby',
        'last_name' => 'Reyes',
        'date_of_birth' => '2026-06-01',
        'time_of_birth' => '09:30',
    ]);

    $response = $this->actingAs($user)->post(route('patients.update-baby', $baby->id), [
        'first_name' => 'Changed',
        'date_of_birth' => '2026-06-01',
        'time_of_birth' => '10:00',
    ]);

    $response->assertForbidden();
    expect($baby->fresh()->first_name)->toBe('Baby');
});

it('AK — updateBaby backend rejects mutation for a legacy REFERRED pregnancy', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient(['status' => 'REFERRED']);
    $baby = Baby::create([
        'patient_id' => $patient->id,
        'first_name' => 'Baby',
        'last_name' => 'Reyes',
        'date_of_birth' => '2026-06-01',
        'time_of_birth' => '09:30',
    ]);

    $response = $this->actingAs($user)->post(route('patients.update-baby', $baby->id), [
        'first_name' => 'Changed',
        'date_of_birth' => '2026-06-01',
        'time_of_birth' => '10:00',
    ]);

    $response->assertForbidden();
    expect($baby->fresh()->first_name)->toBe('Baby');
});

it('AL — delivery mutation stays staff-only (admin gets 403, nothing changes)', function () {
    $admin = pregnancyOutcomeRecordingUser('admin');
    $patient = pregnancyOutcomeRecordingPatient();

    $response = $this->actingAs($admin)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $response->assertForbidden();

    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
    expect($patient->para)->toBe(0);
    expect(Baby::where('patient_id', $patient->id)->exists())->toBeFalse();
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AM — recording a delivery never mutates referral state', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();
    $referral = Referral::create([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'City Health Office',
        'reason' => 'Regular check-up referral',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $response->assertSessionHasNoErrors();

    expect($referral->fresh()->status)->toBe('Pending');
});

it('AN — recording a delivery never mutates prenatal clinical/risk data', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => '2026-07-20',
        'risk_level' => 'HIGH',
        'risk_reasons' => ['PH'],
        'notes' => 'Original clinical notes.',
    ]);

    $response = $this->actingAs($user)->post(route('patients.deliver', $patient->id), pregnancyOutcomeRecordingPayload());
    $response->assertSessionHasNoErrors();

    $visit->refresh();
    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->risk_reasons)->toBe(['PH']);
    expect($visit->notes)->toBe('Original clinical notes.');
});

/*
|--------------------------------------------------------------------------
| Direct service-boundary invariant tests (defense in depth)
|--------------------------------------------------------------------------
| These call PregnancyOutcomeRecordingService directly to prove the core
| delivery-date/baby-DOB invariants are enforced by the authoritative domain
| service itself, independent of controller validation.
*/

it('service H — a valid direct invocation still succeeds end to end', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    $outcome = pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        '2026-08-05',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_HOME,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_PATIENT_REPORT,
        [pregnancyOutcomeRecordingBaby('2026-08-05')],
        'Direct service call note.'
    );

    expect($outcome)->toBeInstanceOf(PregnancyOutcome::class);
    expect($outcome->hasConfirmedOutcome())->toBeTrue();

    $patient->refresh();
    expect($patient->status)->toBe('DELIVERED');
    expect($patient->delivery_date->toDateString())->toBe('2026-08-05');
    expect($patient->para)->toBe(1);
    expect($patient->babies()->count())->toBe(1);
    expect($patient->pregnancyOutcome->confirmed_by)->toBe($user->id);
    expect($patient->pregnancyOutcome->notes)->toBe('Direct service call note.');
});

it('service A G — directly rejects a future delivery date and writes nothing', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        now()->addDay()->toDateString(),
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [pregnancyOutcomeRecordingBaby(now()->toDateString())],
        null
    ))->toThrow(DomainException::class);

    pregnancyOutcomeRecordingAssertNoPartialWrite($patient);
});

it('service B G — directly rejects a malformed delivery date and writes nothing', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        'not-a-real-date',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [pregnancyOutcomeRecordingBaby('2026-08-05')],
        null
    ))->toThrow(DomainException::class);

    pregnancyOutcomeRecordingAssertNoPartialWrite($patient);
});

it('service C G — directly rejects a baby DOB that differs from the delivery date and writes nothing', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        '2026-08-05',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [pregnancyOutcomeRecordingBaby('2026-08-06')],
        null
    ))->toThrow(DomainException::class);

    pregnancyOutcomeRecordingAssertNoPartialWrite($patient);
});

it('service D G — directly rejects a future baby date of birth and writes nothing', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        now()->toDateString(),
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [pregnancyOutcomeRecordingBaby(now()->addDay()->toDateString())],
        null
    ))->toThrow(DomainException::class);

    pregnancyOutcomeRecordingAssertNoPartialWrite($patient);
});

it('service E G — directly rejects a malformed baby date of birth and writes nothing', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        '2026-08-05',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [pregnancyOutcomeRecordingBaby('not-a-baby-date')],
        null
    ))->toThrow(DomainException::class);

    pregnancyOutcomeRecordingAssertNoPartialWrite($patient);
});

it('service F G — directly rejects a missing baby date/time and writes nothing', function () {
    $user = pregnancyOutcomeRecordingUser();
    $patient = pregnancyOutcomeRecordingPatient();

    // Missing time of birth.
    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        '2026-08-05',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [['first_name' => 'Baby', 'date_of_birth' => '2026-08-05']],
        null
    ))->toThrow(DomainException::class);

    // Missing date of birth.
    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        '2026-08-05',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [['first_name' => 'Baby', 'time_of_birth' => '09:30']],
        null
    ))->toThrow(DomainException::class);

    // Empty baby array.
    expect(fn () => pregnancyOutcomeRecordingService()->recordConfirmedDelivery(
        $patient,
        $user,
        '2026-08-05',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [],
        null
    ))->toThrow(DomainException::class);

    pregnancyOutcomeRecordingAssertNoPartialWrite($patient);
});