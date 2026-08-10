<?php

use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Services\PregnancyOutcomeMonitoringService;
use App\Support\PregnancyOutcomeVocabulary;
use Carbon\Carbon;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Deterministic "now" so EDD past/future and the 7-day follow-up window are
 * stable regardless of when the suite runs.
 */
function monitoringAsOf(): Carbon
{
    return Carbon::parse('2026-08-09 12:00:00');
}

function monitoringPatient(array $overrides = []): Patient
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
        'edd' => '2026-08-15',
    ], $overrides));
}

function monitoringOutcome(Patient $patient, array $overrides = []): PregnancyOutcome
{
    return PregnancyOutcome::create(array_merge([
        'patient_id' => $patient->id,
    ], $overrides));
}

function monitoringService(): PregnancyOutcomeMonitoringService
{
    return new PregnancyOutcomeMonitoringService();
}

it('A — a RESOLVED outcome derives from a DELIVERED pregnancy with a confirmed outcome record', function () {
    $patient = monitoringPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-08-01']);
    monitoringOutcome($patient, [
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => monitoringAsOf()->subDays(3),
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_RESOLVED);
});

it('B — a LEGACY_DELIVERED record without a confirmed outcome stays a valid historical record', function () {
    $patient = monitoringPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-06-01']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_LEGACY_DELIVERED);
});

it('C — a REFERRED legacy record derives LEGACY_REFERRED, never an outcome', function () {
    $patient = monitoringPatient(['status' => 'REFERRED']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_LEGACY_REFERRED);
});

it('D — ONGOING with EDD in the future is NOT_YET_DUE', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-15']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE);
});

it('E — ONGOING with EDD today is still NOT_YET_DUE (today <= EDD)', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-09']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE);
});

it('F — ONGOING with a NULL EDD is NOT_YET_DUE (never invented)', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => null]);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE);
});

it('G — ONGOING EDD passed with no outcome record is CONFIRMATION_REQUIRED', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED);
});

it('H — ONGOING EDD passed with a blank outcome row is CONFIRMATION_REQUIRED', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    monitoringOutcome($patient);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED);
});

it('I — fresh STILL_PREGNANT follows within the 7-day window stay STILL_PREGNANT_CONFIRMED', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    monitoringOutcome($patient, [
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => monitoringAsOf()->subDays(6),
        'follow_up_recorded_by' => App\Models\User::factory()->create()->id,
    ]);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_STILL_PREGNANT_CONFIRMED);
});

it('J — fresh UNABLE_TO_CONTACT follows within the 7-day window stay UNABLE_TO_CONTACT', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    monitoringOutcome($patient, [
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_UNABLE_TO_CONTACT,
        'follow_up_recorded_at' => monitoringAsOf()->subDays(2),
    ]);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_UNABLE_TO_CONTACT);
});

it('K — an observation recorded exactly on the 7-day window edge is still fresh', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    monitoringOutcome($patient, [
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => monitoringAsOf()->subDays(7),
    ]);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_STILL_PREGNANT_CONFIRMED);
});

it('L — a stale follow-up (8 days old) expires back to CONFIRMATION_REQUIRED', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    monitoringOutcome($patient, [
        'follow_up_status' => PregnancyOutcomeVocabulary::FOLLOW_UP_STATUS_STILL_PREGNANT_CONFIRMED,
        'follow_up_recorded_at' => monitoringAsOf()->subDays(8),
    ]);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED);
});

it('M — a confirmed outcome on an ONGOING patient surfaces an INVARIANT diagnostic', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-09']);
    monitoringOutcome($patient, [
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => monitoringAsOf()->subDays(3),
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);

    // NEVER rewrites or hides the inconsistency — it is surfaced for review.
    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_INVARIANT_VIOLATION);

    $patient->refresh();
    expect($patient->status)->toBe('ONGOING');
});

it('N — an out-of-range patient status derives the INVARIANT diagnostic, never a fake value', function () {
    $patient = monitoringPatient(['status' => 'UNKNOWN_STATUS', 'edd' => '2026-08-15']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_INVARIANT_VIOLATION);
});

it('O — daysUntilOrPastEdd reports days to and past EDD', function () {
    $future = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-15']);
    $past = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    $none = monitoringPatient(['status' => 'ONGOING', 'edd' => null]);

    expect(monitoringService()->daysUntilOrPastEdd($future, monitoringAsOf()))->toBe(6);
    expect(monitoringService()->daysUntilOrPastEdd($past, monitoringAsOf()))->toBe(-8);
    expect(monitoringService()->daysUntilOrPastEdd($none, monitoringAsOf()))->toBeNull();
});

it('P — stateLabel maps every derived state to a friendly label', function () {
    $labels = PregnancyOutcomeMonitoringService::STATE_LABELS;

    expect($labels[PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED])->toBe('Outcome Confirmation Required');
    expect($labels[PregnancyOutcomeMonitoringService::STATE_STILL_PREGNANT_CONFIRMED])->toBe('Still Pregnant — Confirmed');
    expect($labels[PregnancyOutcomeMonitoringService::STATE_UNABLE_TO_CONTACT])->toBe('Unable to Contact');
    expect($labels[PregnancyOutcomeMonitoringService::STATE_RESOLVED])->toBe('Confirmed Delivery');
    expect($labels[PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE])->toBe('Monitoring Not Yet Due');
    expect($labels[PregnancyOutcomeMonitoringService::STATE_LEGACY_DELIVERED])->toBe('Historical Delivered Record');
    expect($labels[PregnancyOutcomeMonitoringService::STATE_LEGACY_REFERRED])->toBe('Legacy Referred Record');

    expect(PregnancyOutcomeMonitoringService::stateLabel('UNKNOWN_RAW'))->toBe('UNKNOWN_RAW');
});

it('Q — countByState tallies derived states across a population', function () {
    monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']); // confirmation required
    monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-20']); // not yet due
    $delivered = monitoringPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-08-01']);
    monitoringOutcome($delivered, [
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => monitoringAsOf()->subDays(3),
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);
    monitoringPatient(['status' => 'REFERRED']);

    $counts = monitoringService()->countByState(Patient::with('pregnancyOutcome')->get());

    expect($counts[PregnancyOutcomeMonitoringService::STATE_CONFIRMATION_REQUIRED])->toBe(1);
    expect($counts[PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE])->toBe(1);
    expect($counts[PregnancyOutcomeMonitoringService::STATE_RESOLVED])->toBe(1);
    expect($counts[PregnancyOutcomeMonitoringService::STATE_LEGACY_REFERRED])->toBe(1);
});

it('R — only ONGOING without a confirmed outcome and with a passed EDD is follow-up eligible', function () {
    $ongoing = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    $delivered = monitoringPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-08-01']);
    $confirmed = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-01']);
    monitoringOutcome($confirmed, [
        'outcome_type' => PregnancyOutcomeVocabulary::OUTCOME_TYPE_DELIVERED,
        'confirmed_at' => monitoringAsOf()->subDays(3),
        'confirmation_source' => PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
    ]);
    $referred = monitoringPatient(['status' => 'REFERRED']);

    expect(monitoringService()->isFollowUpEligible($ongoing, monitoringAsOf()))->toBeTrue();
    expect(monitoringService()->isFollowUpEligible($delivered, monitoringAsOf()))->toBeFalse();
    expect(monitoringService()->isFollowUpEligible($confirmed, monitoringAsOf()))->toBeFalse();
    expect(monitoringService()->isFollowUpEligible($referred, monitoringAsOf()))->toBeFalse();
});

it('R2 — a future EDD derives NOT_YET_DUE and is not follow-up eligible', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-20']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE);
    expect(monitoringService()->isFollowUpEligible($patient, monitoringAsOf()))->toBeFalse();
});

it('R3 — an EDD today derives NOT_YET_DUE and is not follow-up eligible', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-09']);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE);
    expect(monitoringService()->isFollowUpEligible($patient, monitoringAsOf()))->toBeFalse();
});

it('R4 — a null EDD derives NOT_YET_DUE and is not follow-up eligible', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => null]);

    expect(monitoringService()->deriveState($patient, monitoringAsOf()))
        ->toBe(PregnancyOutcomeMonitoringService::STATE_NOT_YET_DUE);
    expect(monitoringService()->isFollowUpEligible($patient, monitoringAsOf()))->toBeFalse();
});

it('R5 — an EDD yesterday/passed is follow-up eligible when ONGOING and no confirmed outcome', function () {
    $patient = monitoringPatient(['status' => 'ONGOING', 'edd' => '2026-08-08']);

    expect(monitoringService()->isFollowUpEligible($patient, monitoringAsOf()))->toBeTrue();

    // A blank outcome row (no confirmed provenance) stays eligible.
    monitoringOutcome($patient);
    expect(monitoringService()->isFollowUpEligible($patient, monitoringAsOf()))->toBeTrue();
});