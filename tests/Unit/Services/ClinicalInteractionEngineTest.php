<?php

use App\Services\ClinicalInteractionEngine;
use App\ValueObjects\AssessmentContext;
use App\ValueObjects\ClinicalFactorEvidence;

uses(Tests\TestCase::class);

function interactionContext(array $ultrasoundInputs = []): AssessmentContext
{
    return new AssessmentContext(
        patient_id: 1,
        ultrasound_date: '2026-08-01',
        gestational_age: 32,
        patient_status: 'ONGOING',
        ultrasound_inputs: $ultrasoundInputs,
    );
}

function interactionEvidenceFrom(array $factors, array $ultrasoundInputs = []): array
{
    return (new ClinicalInteractionEngine)->evaluate(
        interactionContext($ultrasoundInputs),
        $factors
    );
}

function interactionCodesFrom(array $factors, array $ultrasoundInputs = []): array
{
    return array_column(interactionEvidenceFrom($factors, $ultrasoundInputs), 'code');
}

test('A: BP-H + DM-01 triggers INT-BP-DM', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('BP-H', 150),
        ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
    ]);

    expect($codes)->toContain('INT-BP-DM');
});

test('B: BP-H without DM-01 does not trigger INT-BP-DM', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('BP-H', 150),
    ]);

    expect($codes)->not->toContain('INT-BP-DM');
});

test('C: DM-01 without BP-H does not trigger INT-BP-DM', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
    ]);

    expect($codes)->not->toContain('INT-BP-DM');
});

test('D: DM-01 + US-AF01 with observed HIGH triggers INT-DM-AF', function () {
    $codes = interactionCodesFrom(
        [
            ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-AF01', 'HIGH'),
        ],
        ['amniotic_fluid' => 'HIGH']
    );

    expect($codes)->toContain('INT-DM-AF');
});

test('E: DM-01 + US-AF01 with observed LOW does NOT trigger INT-DM-AF', function () {
    $codes = interactionCodesFrom(
        [
            ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-AF01', 'LOW'),
        ],
        ['amniotic_fluid' => 'LOW']
    );

    expect($codes)->not->toContain('INT-DM-AF');
});

test('F: US-AF01 HIGH without DM-01 produces no INT-DM-AF', function () {
    $codes = interactionCodesFrom(
        [
            ClinicalFactorEvidence::forCode('US-AF01', 'HIGH'),
        ],
        ['amniotic_fluid' => 'HIGH']
    );

    expect($codes)->not->toContain('INT-DM-AF');
});

test('G: DM-01 + US-AF01 with missing or malformed observed AF does not trigger INT-DM-AF', function () {
    $missing = interactionCodesFrom(
        [
            ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-AF01', 'HIGH'),
        ],
        []
    );
    expect($missing)->not->toContain('INT-DM-AF');

    $nullValue = interactionCodesFrom(
        [
            ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-AF01', 'HIGH'),
        ],
        ['amniotic_fluid' => null]
    );
    expect($nullValue)->not->toContain('INT-DM-AF');

    $malformed = interactionCodesFrom(
        [
            ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-AF01', 'HIGH'),
        ],
        ['amniotic_fluid' => 'RIDICULOUS']
    );
    expect($malformed)->not->toContain('INT-DM-AF');
});

test('H: CS-01 + US-P01 (BREEECH) triggers INT-CS-PRES', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('CS-01', 'Yes'),
        ClinicalFactorEvidence::forCode('US-P01', 'BREECH'),
    ]);

    expect($codes)->toContain('INT-CS-PRES');
});

test('I: CS-01 + US-P01 (TRANSVERSE) triggers INT-CS-PRES', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('CS-01', 'Yes'),
        ClinicalFactorEvidence::forCode('US-P01', 'TRANSVERSE'),
    ]);

    expect($codes)->toContain('INT-CS-PRES');
});

test('J: CS-01 + US-P01 (OBLIQUE) triggers INT-CS-PRES', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('CS-01', 'Yes'),
        ClinicalFactorEvidence::forCode('US-P01', 'OBLIQUE'),
    ]);

    expect($codes)->toContain('INT-CS-PRES');
});

test('K: CS-01 without US-P01 produces no INT-CS-PRES', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('CS-01', 'Yes'),
    ]);

    expect($codes)->not->toContain('INT-CS-PRES');
});

test('L: US-P01 without CS-01 produces no INT-CS-PRES', function () {
    $codes = interactionCodesFrom([
        ClinicalFactorEvidence::forCode('US-P01', 'BREECH'),
    ]);

    expect($codes)->not->toContain('INT-CS-PRES');
});

test('M: BP-H + DM-01 + CS-01 + US-P01 preserves both interactions and all four factors', function () {
    $factors = [
        ClinicalFactorEvidence::forCode('BP-H', 150),
        ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
        ClinicalFactorEvidence::forCode('CS-01', 'Yes'),
        ClinicalFactorEvidence::forCode('US-P01', 'BREECH'),
    ];

    $evidence = interactionEvidenceFrom($factors);
    $rows = array_map(static fn ($e) => $e->toArray(), $evidence);

    expect(interactionCodesFrom($factors))->toBe(['INT-BP-DM', 'INT-CS-PRES']);
    expect(array_column($rows, 'required_factor_codes'))->toMatchArray([
        ['BP-H', 'DM-01'],
        ['CS-01', 'US-P01'],
    ]);
});

test('N: repeated evaluation produces no duplicate interaction rows', function () {
    $factors = [
        ClinicalFactorEvidence::forCode('BP-H', 150),
        ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
    ];

    $first = array_map(static fn ($e) => $e->toArray(), interactionEvidenceFrom($factors));
    $second = array_map(static fn ($e) => $e->toArray(), interactionEvidenceFrom($factors));

    expect(array_column($first, 'code'))->toBe(array_unique(array_column($first, 'code')));
    expect($first)->toBe($second);
});

test('O: INT-DM-AF observed_context preserves the gated HIGH amniotic-fluid value', function () {
    $evidence = interactionEvidenceFrom(
        [
            ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-AF01', 'HIGH'),
        ],
        ['amniotic_fluid' => 'HIGH']
    );

    $row = collect($evidence)->firstWhere('code', 'INT-DM-AF');
    expect($row)->not->toBeNull();
    expect($row->observed_context['ultrasound_inputs.amniotic_fluid'])->toBe('HIGH');
});

test('P: INT-DM-AF with observed LOW produces no interaction evidence', function () {
    $evidence = interactionEvidenceFrom(
        [
            ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-AF01', 'LOW'),
        ],
        ['amniotic_fluid' => 'LOW']
    );

    expect(array_column($evidence, 'code'))->not->toContain('INT-DM-AF');
});

test('Q: INT-CS-PRES observed_context preserves the evaluated presentation', function () {
    $evidence = interactionEvidenceFrom(
        [
            ClinicalFactorEvidence::forCode('CS-01', 'Yes'),
            ClinicalFactorEvidence::forCode('US-P01', 'BREECH'),
        ],
        ['presentation' => 'BREECH']
    );

    $row = collect($evidence)->firstWhere('code', 'INT-CS-PRES');
    expect($row)->not->toBeNull();
    expect($row->observed_context['ultrasound_inputs.presentation'])->toBe('BREECH');
});

test('R: INT-BP-DM observed_context carries no duplicated BP snapshot', function () {
    $evidence = interactionEvidenceFrom([
        ClinicalFactorEvidence::forCode('BP-H', 150),
        ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
    ]);

    $row = collect($evidence)->firstWhere('code', 'INT-BP-DM');
    expect($row)->not->toBeNull();
    expect(array_keys($row->observed_context))->not->toContain('blood_pressure');
    expect(array_keys($row->observed_context))->not->toContain('bp_sys');
    expect(array_keys($row->observed_context))->not->toContain('bp_dia');
});

test('every produced interaction is additive: null decision effect, null urgency', function () {
    $factors = [
        ClinicalFactorEvidence::forCode('BP-H', 150),
        ClinicalFactorEvidence::forCode('DM-01', 'Yes'),
        ClinicalFactorEvidence::forCode('CS-01', 'Yes'),
        ClinicalFactorEvidence::forCode('US-P01', 'BREECH'),
    ];

    foreach (interactionEvidenceFrom($factors) as $evidence) {
        expect($evidence->decision_effect)->toBeNull();
        expect($evidence->urgency)->toBeNull();
        expect($evidence->rule_version)->toBe('1.1.0');
    }
});