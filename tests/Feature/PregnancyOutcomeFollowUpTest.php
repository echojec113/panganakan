<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;
use App\Services\PregnancyOutcomeFollowUpService;
use App\Support\PregnancyOutcomeVocabulary;

function followUpUser(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function followUpPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'last_name' => 'Reyes',
        'birthdate' => '1996-05-01',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
        'edd' => now()->subDays(15)->toDateString(),
    ], $overrides));
}

function followUpService(): PregnancyOutcomeFollowUpService
{
    return app(PregnancyOutcomeFollowUpService::class);
}

it('L — a staff member records "still pregnant" for an eligible ONGOING pregnancy', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('pregnancy-outcomes.index'));

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome)->not->toBeNull();
    expect($outcome->follow_up_status)->toBe(PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED);
});

it('M — a staff member records "unable to contact" for an eligible ONGOING pregnancy', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.unable-to-contact', $patient->id));

    $response->assertSessionHasNoErrors();

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->follow_up_status)->toBe(PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT);
});

it('N — the observation is stamped with the acting staff id and the server clock', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    $before = now()->subSecond();
    $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->follow_up_recorded_by)->toBe($user->id);
    expect($outcome->follow_up_recorded_at)->not->toBeNull();
    expect($outcome->follow_up_recorded_at->gte($before))->toBeTrue();
});

it('O — a first observation creates the single outcome row', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    expect(PregnancyOutcome::where('patient_id', $patient->id)->count())->toBe(1);
});

it('P — a follow-up reuses the existing blank placeholder row in place', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    PregnancyOutcome::create(['patient_id' => $patient->id]);

    $this->actingAs($user)->post(route('pregnancy-outcomes.unable-to-contact', $patient->id));

    expect(PregnancyOutcome::where('patient_id', $patient->id)->count())->toBe(1);
});

it('Q R — a new observation replaces the previous one (schema stores only the latest)', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));
    $this->actingAs($user)->post(route('pregnancy-outcomes.unable-to-contact', $patient->id));

    expect(PregnancyOutcome::where('patient_id', $patient->id)->count())->toBe(1);
    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->follow_up_status)->toBe(PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT);
});

it('S T U — recording a follow-up never touches patient lifespan, delivery date, or para', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
    expect($patient->delivery_date)->toBeNull();
    expect($patient->para)->toBe(1);
});

it('V — recording a follow-up never mutates referral state', function () {
    $user = followUpUser();
    $patient = followUpPatient();
    $referral = Referral::create([
        'patient_id' => $patient->id,
        'created_by' => $user->id,
        'referred_to' => 'City Health Office',
        'reason' => 'Regular check-up referral',
        'referral_date' => now()->toDateString(),
        'status' => 'Pending',
    ]);

    $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    expect($referral->fresh()->status)->toBe('Pending');
});

it('AA — follow-up never mutates prenatal clinical/risk data', function () {
    $user = followUpUser();
    $patient = followUpPatient();
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->subMonths(2)->toDateString(),
        'risk_level' => 'HIGH',
        'risk_reasons' => ['PH'],
        'notes' => 'Original clinical notes.',
    ]);

    $this->actingAs($user)->post(route('pregnancy-outcomes.unable-to-contact', $patient->id));

    $visit->refresh();
    expect($visit->risk_level)->toBe('HIGH');
    expect($visit->risk_reasons)->toBe(['PH']);
    expect($visit->notes)->toBe('Original clinical notes.');
});

it('W — a DELIVERED pregnancy rejects follow-up observations', function () {
    $user = followUpUser();
    $patient = followUpPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(10)->toDateString()]);

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $response->assertSessionHasErrors('status');
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
    expect($patient->fresh()->status)->toBe('DELIVERED');
});

it('X — a legacy REFERRED record rejects follow-up observations', function () {
    $user = followUpUser();
    $patient = followUpPatient(['status' => 'REFERRED']);

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $response->assertSessionHasErrors('status');
    expect($patient->fresh()->status)->toBe('REFERRED');
});

it('Y — a confirmed outcome rejects further follow-up observations', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    PregnancyOutcome::create([
        'patient_id' => $patient->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => now(),
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.unable-to-contact', $patient->id));

    $response->assertSessionHasErrors('status');
    expect($patient->refresh()->pregnancyOutcome->follow_up_status)->toBeNull();
});

it('Z — an admin cannot record follow-up observations (staff-only)', function () {
    $admin = followUpUser('admin');
    $patient = followUpPatient();

    $response = $this->actingAs($admin)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $response->assertForbidden();
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AB — staff cannot forge the observation, the timestamp, or the actor', function () {
    $user = followUpUser();
    $other = followUpUser();
    $patient = followUpPatient();

    $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id), [
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT,
        'follow_up_recorded_at' => '2020-01-01 00:00:00',
        'follow_up_recorded_by' => $other->id,
    ]);

    $outcome = $patient->refresh()->pregnancyOutcome;
    expect($outcome->follow_up_status)->toBe(PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED);
    expect($outcome->follow_up_recorded_at->toDateString())->toBe(now()->toDateString());
    expect($outcome->follow_up_recorded_by)->toBe($user->id);
});

it('AC — successful follow-up transitions are recorded in the AuditLog, not abused in notes', function () {
    $user = followUpUser();
    $patient = followUpPatient();

    $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $audit = AuditLog::where('module', 'PATIENT')->orderByDesc('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->description)->toContain('Recorded follow-up observation for patient #' . $patient->id);

    // notes is outcome-level provenance, never follow-up narrative.
    expect($patient->refresh()->pregnancyOutcome->notes)->toBeNull();
});

it('AD — a failed (rejected) transition leaves the record untouched and writes no audit', function () {
    $user = followUpUser();
    $patient = followUpPatient(['status' => 'DELIVERED', 'delivery_date' => now()->subDays(10)->toDateString()]);

    $before = AuditLog::count();
    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $response->assertSessionHasErrors('status');
    expect(AuditLog::count())->toBe($before);
});

it('AE — direct service invocation for a FUTURE EDD throws DomainException and changes nothing', function () {
    $user = followUpUser();
    $patient = followUpPatient(['edd' => now()->addDays(10)->toDateString()]);

    expect(fn () => followUpService()->recordStillPregnant($patient, $user))
        ->toThrow(DomainException::class);

    expect($patient->fresh()->status)->toBe('ONGOING');
    expect($patient->fresh()->delivery_date)->toBeNull();
    expect($patient->fresh()->para)->toBe(1);
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AF — direct service invocation for an EDD TODAY throws DomainException and changes nothing', function () {
    $user = followUpUser();
    $patient = followUpPatient(['edd' => now()->toDateString()]);

    expect(fn () => followUpService()->recordUnableToContact($patient, $user))
        ->toThrow(DomainException::class);

    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
    expect($patient->fresh()->status)->toBe('ONGOING');
});

it('AG — direct service invocation for a NULL EDD throws DomainException and changes nothing', function () {
    $user = followUpUser();
    $patient = followUpPatient(['edd' => null]);

    expect(fn () => followUpService()->recordStillPregnant($patient, $user))
        ->toThrow(DomainException::class);

    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
    expect($patient->fresh()->status)->toBe('ONGOING');
});

it('AH — direct POST for a FUTURE EDD writes no follow-up observation', function () {
    $user = followUpUser();
    $patient = followUpPatient(['edd' => now()->addDays(10)->toDateString()]);

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.still-pregnant', $patient->id));

    $response->assertSessionHasErrors('status');
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AI — direct POST for an EDD TODAY writes no follow-up observation', function () {
    $user = followUpUser();
    $patient = followUpPatient(['edd' => now()->toDateString()]);

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.unable-to-contact', $patient->id));

    $response->assertSessionHasErrors('status');
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});

it('AJ — direct POST for a NULL EDD writes no follow-up observation', function () {
    $user = followUpUser();
    $patient = followUpPatient(['edd' => null]);

    $response = $this->actingAs($user)->post(route('pregnancy-outcomes.unable-to-contact', $patient->id));

    $response->assertSessionHasErrors('status');
    expect(PregnancyOutcome::where('patient_id', $patient->id)->exists())->toBeFalse();
});