<?php

use App\Support\PregnancyOutcomeVocabulary;

it('accepts DELIVERED as the only confirmed outcome type', function () {
    expect(PregnancyOutcomeVocabulary::isValidOutcomeType('DELIVERED'))->toBeTrue();
});

it('rejects clinically sensitive and non-DELIVERED outcome vocabulary', function () {
    foreach ([
        'MISCARRIAGE',
        'STILLBIRTH',
        'ABORTION',
        'MATERNAL_DEATH',
        'NEONATAL_DEATH',
        'CLOSED',
        'delivered',
    ] as $value) {
        expect(PregnancyOutcomeVocabulary::isValidOutcomeType($value))->toBeFalse("$value must be rejected");
    }
});

it('accepts exactly the four approved delivery locations', function () {
    expect(PregnancyOutcomeVocabulary::DELIVERY_LOCATIONS)->toBe([
        'THIS_CLINIC',
        'ANOTHER_FACILITY',
        'HOME',
        'OTHER',
    ]);

    foreach (['THIS_CLINIC', 'ANOTHER_FACILITY', 'HOME', 'OTHER'] as $location) {
        expect(PregnancyOutcomeVocabulary::isValidDeliveryLocation($location))->toBeTrue($location);
    }

    expect(PregnancyOutcomeVocabulary::isValidDeliveryLocation('PROVINCIAL_HOSPITAL'))->toBeFalse();
});

it('persists only the two follow-up observations and rejects derived ones', function () {
    expect(PregnancyOutcomeVocabulary::FOLLOW_UP_STATUSES)->toBe([
        'STILL_PREGNANT_CONFIRMED',
        'UNABLE_TO_CONTACT',
    ]);

    expect(PregnancyOutcomeVocabulary::isValidFollowUpStatus('STILL_PREGNANT_CONFIRMED'))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidFollowUpStatus('UNABLE_TO_CONTACT'))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidFollowUpStatus('CONFIRMATION_REQUIRED'))->toBeFalse();
    expect(PregnancyOutcomeVocabulary::isValidFollowUpStatus('RESOLVED'))->toBeFalse();
});

it('labels CONFIRMATION_REQUIRED / RESOLVED as derived (never persisted)', function () {
    expect(PregnancyOutcomeVocabulary::isDerivedFollowUpStatus('CONFIRMATION_REQUIRED'))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isDerivedFollowUpStatus('RESOLVED'))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isDerivedFollowUpStatus('STILL_PREGNANT_CONFIRMED'))->toBeFalse();
    expect(PregnancyOutcomeVocabulary::isDerivedFollowUpStatus('UNABLE_TO_CONTACT'))->toBeFalse();
});

it('keeps the confirmation-source taxonomy minimal', function () {
    expect(PregnancyOutcomeVocabulary::CONFIRMATION_SOURCES)->toBe([
        'CLINIC_RECORD',
        'PATIENT_REPORT',
        'OTHER_FACILITY_REPORT',
        'OTHER',
    ]);

    expect(PregnancyOutcomeVocabulary::isValidConfirmationSource('CLINIC_RECORD'))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidConfirmationSource('PATIENT_REPORT'))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidConfirmationSource('OTHER_FACILITY_REPORT'))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidConfirmationSource('OTHER'))->toBeTrue();

    expect(PregnancyOutcomeVocabulary::isValidConfirmationSource('BIRTH_CERTIFICATE'))->toBeFalse();
    expect(PregnancyOutcomeVocabulary::isValidConfirmationSource('REFERRAL_FACILITY_REPORT'))->toBeFalse();
});

it('accepts null as the unrecorded (no evidence) state in every vocabulary', function () {
    expect(PregnancyOutcomeVocabulary::isValidOutcomeType(null))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidDeliveryLocation(null))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidFollowUpStatus(null))->toBeTrue();
    expect(PregnancyOutcomeVocabulary::isValidConfirmationSource(null))->toBeTrue();
});