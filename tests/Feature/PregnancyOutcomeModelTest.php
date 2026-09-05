<?php

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\User;
use App\Support\PregnancyOutcomeVocabulary;
use Illuminate\Support\Carbon;

/** "what happened == B <=> outcome_type != null" contract tests. */

function pregnancyOutcomeModelPatient(string $firstName = 'Outcome', string $status = 'ONGOING'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => 'Test',
        'age' => 27,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => $firstName . '@example.com',
        'gravida' => 1,
        'para' => 0,
        'status' => $status,
    ]);
}

it('relates the record to its pregnancy in both directions', function () {
    $pregnancy = pregnancyOutcomeModelPatient('Relate');
    $outcome = PregnancyOutcome::create(['patient_id' => $pregnancy->id]);

    expect($outcome->patient->is($pregnancy))->toBeTrue();
    expect($pregnancy->pregnancyOutcome->is($outcome))->toBeTrue();
});

it('casts follow-up and confirmation provenance timestamps as Carbon', function () {
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('Cast')->id,
        'follow_up_recorded_at' => '2026-08-09 10:00:00',
        'confirmed_at' => '2026-08-09 10:05:00',
    ]);

    expect($outcome->follow_up_recorded_at)->toBeInstanceOf(Carbon::class);
    expect($outcome->confirmed_at)->toBeInstanceOf(Carbon::class);
});

it('represents the null/unrecorded state with every fact column null', function () {
    $outcome = PregnancyOutcome::create(['patient_id' => pregnancyOutcomeModelPatient('Blank')->id]);

    expect($outcome->outcome_type)->toBeNull();
    expect($outcome->delivery_location)->toBeNull();
    expect($outcome->follow_up_status)->toBeNull();
    expect($outcome->confirmation_source)->toBeNull();
    expect($outcome->confirmed_at)->toBeNull();
    expect($outcome->confirmed_by)->toBeNull();
    expect($outcome->hasConfirmedOutcome())->toBeFalse();
});

it('persists STILL_PREGNANT_CONFIRMED with a recording time and actor', function () {
    $user = User::factory()->create();
    $at = now();

    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('StillPregnant')->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => $at,
        'follow_up_recorded_by' => $user->id,
    ]);

    $fresh = $outcome->fresh();
    expect($fresh->follow_up_status)->toBe('STILL_PREGNANT_CONFIRMED');
    expect($fresh->follow_up_recorded_at->format('Y-m-d H:i:s'))->toBe($at->format('Y-m-d H:i:s'));
    expect($fresh->followUpRecordedBy->is($user))->toBeTrue();
    expect($fresh->hasConfirmedOutcome())->toBeFalse();
});

it('persists UNABLE_TO_CONTACT with a recording time and actor', function () {
    $user = User::factory()->create();
    $at = now();

    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('NoContact')->id,
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT,
        'follow_up_recorded_at' => $at,
        'follow_up_recorded_by' => $user->id,
    ]);

    $fresh = $outcome->fresh();
    expect($fresh->follow_up_status)->toBe('UNABLE_TO_CONTACT');
    expect($fresh->follow_up_recorded_at->format('Y-m-d H:i:s'))->toBe($at->format('Y-m-d H:i:s'));
    expect($fresh->followUpRecordedBy->is($user))->toBeTrue();
    expect($fresh->hasConfirmedOutcome())->toBeFalse();
});

it('persists a confirmed DELIVERED outcome with delivery context and provenance', function () {
    $user = User::factory()->create();
    $at = now();

    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('Delivered')->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'delivery_location' => PregnancyOutcomeVocabulary::DELIVERY_LOCATION_ANOTHER_FACILITY,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_OTHER_FACILITY_REPORT,
        'confirmed_at' => $at,
        'confirmed_by' => $user->id,
        'notes' => 'Confirmed via the provincial hospital discharge report.',
    ]);

    $fresh = $outcome->fresh();
    expect($fresh->hasConfirmedOutcome())->toBeTrue();
    expect($fresh->outcome_type)->toBe('DELIVERED');
    expect($fresh->delivery_location)->toBe('ANOTHER_FACILITY');
    expect($fresh->confirmation_source)->toBe('OTHER_FACILITY_REPORT');
    expect($fresh->confirmed_at->format('Y-m-d H:i:s'))->toBe($at->format('Y-m-d H:i:s'));
    expect($fresh->confirmedBy->is($user))->toBeTrue();
    expect($fresh->notes)->toContain('provincial hospital');
});

it('does not offer a mass-assignable delivery_date on the outcome record', function () {
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('NoDupDate')->id,
        'delivery_date' => '2026-08-01',
    ]);

    expect($outcome->delivery_date)->toBeNull();
    expect($outcome->fresh()->delivery_date)->toBeNull();
});

it('A. does not confirm an outcome when only outcome_type is set', function () {
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('ConfirmA')->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
    ]);

    expect($outcome->hasConfirmedOutcome())->toBeFalse();
});

it('B. does not confirm an outcome with outcome_type + confirmed_at but no source', function () {
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('ConfirmB')->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => now(),
    ]);

    expect($outcome->hasConfirmedOutcome())->toBeFalse();
});

it('C. confirms an outcome with outcome_type + confirmed_at + source even when confirmed_by is null', function () {
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('ConfirmC')->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => now(),
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);

    expect($outcome->confirmed_by)->toBeNull();
    expect($outcome->hasConfirmedOutcome())->toBeTrue();
});

it('D. confirms an outcome with full provenance including confirmed_by', function () {
    $user = User::factory()->create();
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('ConfirmD')->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => now(),
        'confirmed_by' => $user->id,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);

    expect($outcome->hasConfirmedOutcome())->toBeTrue();
});

it('E. historical confirmation survives the confirming user being force-deleted', function () {
    $user = User::factory()->create();
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('ConfirmE')->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => '2026-08-09 10:00:00',
        'confirmed_by' => $user->id,
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);

    expect($outcome->hasConfirmedOutcome())->toBeTrue();

    $user->forceDelete();

    $fresh = $outcome->fresh();
    expect($fresh->confirmed_by)->toBeNull();
    expect($fresh->outcome_type)->toBe('DELIVERED');
    expect($fresh->confirmed_at)->not->toBeNull();
    expect($fresh->confirmation_source)->toBe('CLINIC_RECORD');
    expect($fresh->hasConfirmedOutcome())->toBeTrue();
});

it('F. does not confirm an outcome when confirmation_source is missing', function () {
    $user = User::factory()->create();
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('ConfirmF')->id,
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => now(),
        'confirmed_by' => $user->id,
    ]);

    expect($outcome->hasConfirmedOutcome())->toBeFalse();
});

it('G. does not confirm an outcome in the null/unrecorded state', function () {
    $outcome = PregnancyOutcome::create([
        'patient_id' => pregnancyOutcomeModelPatient('ConfirmG')->id,
    ]);

    expect($outcome->hasConfirmedOutcome())->toBeFalse();
});

it('leaves a legacy DELIVERED patient valid without an outcome record', function () {
    Patient::where('id', pregnancyOutcomeModelPatient('LegacyDelivered', 'DELIVERED')->id)
        ->update(['delivery_date' => '2026-06-01']);

    $patient = Patient::where('first_name', 'LegacyDelivered')->first();

    expect($patient->status)->toBe('DELIVERED');
    expect($patient->delivery_date->toDateString())->toBe('2026-06-01');
    expect($patient->pregnancyOutcome)->toBeNull();
});

it('leaves an ONGOING patient valid without an outcome record', function () {
    $patient = pregnancyOutcomeModelPatient('OngoingNoOutcome', 'ONGOING');

    expect($patient->status)->toBe('ONGOING');
    expect($patient->pregnancyOutcome)->toBeNull();
});

it('leaves a legacy REFERRED patient untouched', function () {
    $patient = pregnancyOutcomeModelPatient('LegacyReferred', 'REFERRED');

    expect($patient->status)->toBe('REFERRED');
    expect($patient->pregnancyOutcome)->toBeNull();
});