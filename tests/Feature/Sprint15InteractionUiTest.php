<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function interactionUiPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Inter',
        'last_name' => 'Action',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function interactionUiVisit(int $patientId, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 24,
        'risk_level' => 'HIGH',
        'decision_source' => 'RULE_BASED',
        'rule_reasons' => ['Diabetes'],
    ], $overrides));
}

function intWith(string $code, array $overrides = []): array
{
    $base = match ($code) {
        'INT-CS-PRES' => [
            'code' => 'INT-CS-PRES',
            'label' => 'Previous cesarean with abnormal fetal presentation',
            'required_factor_codes' => ['CS-01', 'US-P01'],
            'observed_context' => ['ultrasound_inputs.presentation' => 'BREECH'],
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'A previous cesarean section and an abnormal fetal presentation were both identified. Combined, these strengthen the need for hospital-level obstetric birth-planning and referral review; the CDSS does not determine cesarean, mode of birth, or VBAC eligibility.',
            'suggested_action' => 'Arrange hospital-level obstetric birth-planning and referral review.',
            'rule_version' => '1.1.0',
        ],
        'INT-DM-AF' => [
            'code' => 'INT-DM-AF',
            'label' => 'Diabetes with high amniotic fluid',
            'required_factor_codes' => ['DM-01', 'US-AF01'],
            'observed_context' => ['ultrasound_inputs.amniotic_fluid' => 'HIGH'],
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'A high amniotic-fluid finding and diabetes were identified alongside each other.',
            'suggested_action' => 'Coordinate qualified review of diabetes care and the high amniotic-fluid ultrasound finding.',
            'rule_version' => '1.1.0',
        ],
        'INT-BP-DM' => [
            'code' => 'INT-BP-DM',
            'label' => 'Elevated blood pressure with diabetes',
            'required_factor_codes' => ['BP-H', 'DM-01'],
            'observed_context' => [],
            'decision_effect' => null,
            'urgency' => null,
            'explanation' => 'Both an elevated blood-pressure finding and diabetes were independently identified. It does not diagnose pre-eclampsia.',
            'suggested_action' => 'Coordinate qualified clinical review of the elevated blood-pressure and diabetes findings.',
            'rule_version' => '1.1.0',
        ],
        default => ['code' => $code, 'label' => 'Unknown', 'observed_context' => []],
    };

    return array_merge($base, $overrides);
}

beforeEach(function () {
    $this->staff = User::factory()->create(['role' => 'staff']);
});

test('A: patient profile shows Clinical Interactions Identified when interaction evidence exists', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'factor_evidence' => [\App\ValueObjects\ClinicalFactorEvidence::forCode('DM-01', true)],
        'assessment_metadata' => [
            'interaction_evidence' => [intWith('INT-CS-PRES')],
            'decision_trace' => [
                [
                    'step_code' => 'INTERACTION_RULE_EVALUATION',
                    'status' => 'TRIGGERED',
                    'summary' => 'One or more ACTIVE additive interactions combined triggered factors into explainable evidence.',
                    'related_interaction_codes' => ['INT-CS-PRES'],
                    'assessed_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Clinical Interactions Identified');
});

test('B: INT-CS-PRES label, code, and explanation render on the patient profile', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [intWith('INT-CS-PRES')],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Previous cesarean with abnormal fetal presentation');
    $response->assertSee('INT-CS-PRES');
    $response->assertSeeText('A previous cesarean section and an abnormal fetal presentation were both identified.');
});

test('C: observed presentation context renders as clinician-friendly BREECH, not raw JSON', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [intWith('INT-CS-PRES')],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Fetal presentation: BREECH');
    $response->assertDontSee('ultrasound_inputs.presentation');
});

test('D: INT-DM-AF renders a HIGH amniotic-fluid evaluated finding', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [intWith('INT-DM-AF')],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Diabetes with high amniotic fluid');
    $response->assertSeeText('Amniotic fluid: HIGH');
});

test('E: INT-BP-DM renders without inventing duplicated BP snapshot values', function () {
    $patient = Patient::create([
        'first_name' => 'Bpdia', 'last_name' => 'Int', 'age' => 30,
        'gravida' => 1, 'para' => 0, 'status' => 'ONGOING',
    ]);
    interactionUiVisit($patient->id, [
        'bp_sys' => 150,
        'bp_dia' => 95,
        'assessment_metadata' => [
            'interaction_evidence' => [intWith('INT-BP-DM')],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Elevated blood pressure with diabetes');
    $response->assertSeeText('INT-BP-DM');
    // The interaction card must not invent an observed-context BP snapshot
    $response->assertDontSee('bp_sys');
    $response->assertDontSee('Evaluated finding');
});

test('F: multiple interactions render as separate non-overwriting cards', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'factor_evidence' => [
            \App\ValueObjects\ClinicalFactorEvidence::forCode('BP-H', true),
            \App\ValueObjects\ClinicalFactorEvidence::forCode('DM-01', true),
        ],
        'assessment_metadata' => [
            'interaction_evidence' => [
                intWith('INT-BP-DM'),
                intWith('INT-CS-PRES'),
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Elevated blood pressure with diabetes');
    $response->assertSeeText('Previous cesarean with abnormal fetal presentation');
    expect(substr_count($response->getContent(), 'INT-CS-PRES'))->toBeGreaterThanOrEqual(1);
    expect(substr_count($response->getContent(), 'INT-BP-DM'))->toBeGreaterThanOrEqual(1);
});

test('G: standalone Clinical Factors Identified still renders separately', function () {
    $patient = interactionUiPatient(['previous_cs' => 1]);
    interactionUiVisit($patient->id, [
        'rule_reasons' => ['Previous cesarean section'],
        'factor_evidence' => [
            \App\ValueObjects\ClinicalFactorEvidence::forCode('CS-01', true),
            \App\ValueObjects\ClinicalFactorEvidence::forCode('US-P01', true),
        ],
        'assessment_metadata' => [
            'interaction_evidence' => [intWith('INT-CS-PRES')],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Clinical Factors Identified');
    $response->assertSee('Clinical Interactions Identified');
});

test('G2: no interaction section when interaction_evidence is empty array', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [],
            'decision_trace' => [
                [
                    'step_code' => 'INTERACTION_RULE_EVALUATION',
                    'status' => 'COMPLETED',
                    'summary' => 'No ACTIVE clinical interaction triggered; the interaction check completed.',
                    'related_interaction_codes' => [],
                    'assessed_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertDontSee('Clinical Interactions Identified');
    $response->assertSee('No ACTIVE clinical interaction triggered');
});

test('H: legacy null assessment_metadata does not crash', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => null,
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
});

test('J: malformed/unknown interaction evidence is skipped conservatively without crashing', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [
                ['foo' => 'bar'],
                ['code' => 'INT-NOT-REAL', 'label' => 'Unknown', 'required_factor_codes' => []],
                intWith('INT-DM-AF'),
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSeeText('Diabetes with high amniotic fluid');
    $response->assertDontSee('INT-NOT-REAL');
});

test('K: decision path shows TRIGGERED interaction codes', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [
                intWith('INT-BP-DM'),
                intWith('INT-CS-PRES'),
            ],
            'decision_trace' => [
                [
                    'step_code' => 'INTERACTION_RULE_EVALUATION',
                    'status' => 'TRIGGERED',
                    'summary' => 'One or more ACTIVE additive interactions combined ' . 'triggered factors into explainable evidence.',
                    'related_interaction_codes' => ['INT-BP-DM', 'INT-CS-PRES'],
                    'assessed_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('Assessment Decision Path');
    $response->assertSee('INT-BP-DM');
    $response->assertSee('INT-CS-PRES');
});

test('L: BP-URG SKIPPED interaction trace step renders correctly', function () {
    $patient = Patient::create([
        'first_name' => 'Urg', 'last_name' => 'Int', 'age' => 34,
        'gravida' => 3, 'para' => 2, 'status' => 'ONGOING',
    ]);
    interactionUiVisit($patient->id, [
        'urgency' => 'URGENT_CLINICAL_REVIEW',
        'assessment_metadata' => [
            'interaction_evidence' => [],
            'decision_trace' => [
                [
                    'step_code' => 'INTERACTION_RULE_EVALUATION',
                    'status' => 'SKIPPED',
                    'summary' => 'Skipped because the severe blood-pressure safety assessment already established the result.',
                    'related_interaction_codes' => [],
                    'assessed_at' => now()->toIso8601String(),
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertSee('URGENT CLINICAL REVIEW');
    $response->assertSee('SKIPPED');
});

test('N: risk monitoring shows a compact interaction badge without breaking the table', function () {
    $patient = Patient::create([
        'first_name' => 'Monitor', 'last_name' => 'Int', 'age' => 29,
        'gravida' => 1, 'para' => 0, 'status' => 'ONGOING',
        'assigned_staff_id' => $this->staff->id,
    ]);
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [
                intWith('INT-BP-DM'),
                intWith('INT-CS-PRES'),
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('risk.monitoring'));

    $response->assertOk();
    expect(substr_count($response->getContent(), '2 interactions'))->toBeGreaterThanOrEqual(1);
});

test('KP: printable/export view includes interaction evidence', function () {
    $patient = interactionUiPatient();
    $latestVisit = interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [intWith('INT-CS-PRES')],
        ],
    ]);

    $html = view('exports.patient-record', compact('patient', 'latestVisit'))->render();

    expect($html)->toContain('Clinical Interactions Identified');
    expect($html)->toContain('INT-CS-PRES');
    expect($html)->toContain('Previous cesarean with abnormal fetal presentation');
});

test('O: UI does not introduce dangerous escalation or diagnosis terms', function () {
    $patient = interactionUiPatient();
    interactionUiVisit($patient->id, [
        'assessment_metadata' => [
            'interaction_evidence' => [
                intWith('INT-BP-DM'),
                intWith('INT-DM-AF'),
                intWith('INT-CS-PRES'),
            ],
        ],
    ]);

    $response = $this->actingAs($this->staff)->get(route('patients.show', $patient->id));

    $response->assertOk();
    $response->assertDontSeeText('VERY HIGH');
    $response->assertDontSeeText('MODERATE');
    $response->assertDontSeeText('EXTREME');
    $response->assertDontSeeText('risk score');
    $response->assertDontSeeText('requires cesarean');
    $response->assertDontSeeText('must deliver immediately');
});